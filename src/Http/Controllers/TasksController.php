<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Support\ListingColumns;
use RadThemes\RadpackCrm\Support\Tasks;
use Statamic\CP\PublishForm;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Statamic;

class TasksController extends CpController
{
    public const VIEWS = ['mine', 'open', 'overdue', 'done', 'all'];

    public function index(Request $request): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Tasks/Index', [
            'columns' => ListingColumns::for('tasks'),
            'jsonUrl' => cp_route('radpack-crm.tasks.json'),
            'createUrl' => cp_route('radpack-crm.tasks.create'),
            'calendarUrl' => cp_route('radpack-crm.calendar'),
            'actionUrl' => cp_route('radpack-crm.tasks.actions.run'),
            'view' => in_array($request->input('view'), self::VIEWS, true) ? $request->input('view') : 'mine',
            'views' => [
                ['value' => 'mine', 'label' => __('My open tasks')],
                ['value' => 'open', 'label' => __('All open tasks')],
                ['value' => 'overdue', 'label' => __('Overdue')],
                ['value' => 'done', 'label' => __('Done')],
                ['value' => 'all', 'label' => __('Everything')],
            ],
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        $this->authorize('view crm');

        $query = Task::query()->with(['contact', 'company']);

        match ($request->input('view', 'mine')) {
            'mine' => $query->open()->where('assigned_to', User::current()->id()),
            'open' => $query->open(),
            'overdue' => $query->overdue(),
            'done' => $query->whereNotNull('completed_at'),
            default => null,
        };

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($contact) => $contact->search($search)));
        }

        $sort = in_array($request->input('sort'), ['title', 'starts_at', 'priority', 'completed_at'], true) ? $request->input('sort') : 'starts_at';
        $direction = $request->input('order') === 'desc' ? 'desc' : 'asc';
        $query->orderByRaw('starts_at is null')->orderBy($sort, $direction)->orderBy('id');

        $page = $query->paginate(Statamic::cpPerPage($request->input('perPage')));

        return response()->json([
            'data' => collect($page->items())->map(fn (Task $task) => Tasks::toArray($task))->all(),
            'meta' => [
                'columns' => ListingColumns::fromRequest($request, 'tasks'),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'links' => [],
        ]);
    }

    public function create(Request $request): PublishForm
    {
        $this->authorize('edit crm');

        $contact = $request->filled('contact') ? Contact::find($request->integer('contact')) : null;
        $start = $request->filled('date') ? Carbon::parse($request->input('date'))->setTime(9, 0) : null;

        return PublishForm::make(Task::blueprint())
            ->icon('checkbox')
            ->title(__('Create Task'))
            ->values(array_filter([
                'type' => 'task',
                'priority' => 'normal',
                'assigned_to' => [User::current()->id()],
                'contact' => $contact ? [$contact->id] : null,
                'company' => $request->filled('company') ? [$request->integer('company')] : ($contact?->company_id ? [$contact->company_id] : null),
                'starts_at' => $start?->format('Y-m-d H:i'),
            ]))
            ->submittingTo(cp_route('radpack-crm.tasks.store'), 'POST');
    }

    /**
     * @return array{redirect: string}
     */
    public function store(Request $request): array
    {
        $this->authorize('edit crm');

        $task = (new Task)->fillFromBlueprint(PublishForm::make(Task::blueprint())->submit($request->all()));
        $task->save();

        return ['redirect' => $task->contact ? cp_route('radpack-crm.contacts.show', $task->contact) : cp_route('radpack-crm.tasks.index')];
    }

    public function edit(Task $task): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Task::blueprint())
            ->icon('checkbox')
            ->title($task->title)
            ->values($task->blueprintValues())
            ->submittingTo(cp_route('radpack-crm.tasks.update', $task));
    }

    /**
     * @return array{redirect: string}
     */
    public function update(Request $request, Task $task): array
    {
        $this->authorize('edit crm');

        $task->fillFromBlueprint(PublishForm::make(Task::blueprint())->submit($request->all()))->save();

        return ['redirect' => cp_route('radpack-crm.tasks.index')];
    }

    public function toggle(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('edit crm');

        $task->complete($request->boolean('done', ! $task->isDone()));

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete crm');

        $task->delete();

        return redirect()->to(cp_route('radpack-crm.tasks.index'));
    }
}
