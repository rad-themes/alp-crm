<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Http\Resources\ContactResource;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Support\Presenter;
use RadThemes\RadpackCrm\Support\Sales;
use Statamic\CP\PublishForm;
use Statamic\Facades\Scope;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use Statamic\Statamic;

class ContactsController extends CpController
{
    use QueriesFilters;

    private const SORTABLE = ['name', 'email', 'status', 'created_at', 'last_contacted_at'];

    public function index(): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Contacts/Index', [
            'filters' => Scope::filters('radpack-crm.contacts'),
            'jsonUrl' => cp_route('radpack-crm.contacts.json'),
            'actionUrl' => cp_route('radpack-crm.contacts.actions.run'),
            'createUrl' => cp_route('radpack-crm.contacts.create'),
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function json(FilteredRequest $request): JsonResponse
    {
        $this->authorize('view crm');

        $query = Contact::query()->with(['company', 'tags']);

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $badges = $this->queryFilters($query, $request->filters);

        $sort = in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('order') === 'asc' ? 'asc' : 'desc';

        foreach ($sort === 'name' ? ['first_name', 'last_name'] : [$sort] as $column) {
            $query->orderBy($column, $direction);
        }

        return ContactResource::collection($query->paginate(Statamic::cpPerPage($request->input('perPage'))))
            ->additional(['meta' => ['activeFilterBadges' => $badges]])
            ->response();
    }

    /**
     * Search contacts for pickers, e.g. the client field on quotes and invoices.
     *
     * @return array<int, array{value: int, label: string, company: ?string}>
     */
    public function search(Request $request): array
    {
        $this->authorize('view crm');

        return Contact::query()
            ->with('company')
            ->when($request->input('q'), fn ($query, $term) => $query->search($term))
            ->orderBy('first_name')->orderBy('last_name')
            ->limit(20)
            ->get()
            ->map(fn (Contact $contact) => [
                'value' => $contact->id,
                'label' => $contact->name().($contact->company ? " · {$contact->company->name}" : ''),
                'company' => $contact->company?->name,
            ])
            ->all();
    }

    public function create(): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Contact::blueprint())
            ->icon('users')
            ->title(__('Create Contact'))
            ->values(['status' => 'lead'])
            ->submittingTo(cp_route('radpack-crm.contacts.store'), 'POST');
    }

    /**
     * @return array{redirect: string}
     */
    public function store(Request $request): array
    {
        $this->authorize('edit crm');

        $contact = (new Contact)->fillFromBlueprint(PublishForm::make(Contact::blueprint())->submit($request->all()));
        $contact->save();

        return ['redirect' => cp_route('radpack-crm.contacts.show', $contact)];
    }

    public function show(Contact $contact): Response
    {
        $this->authorize('view crm');

        $contact->load(['company', 'tags', 'aliases', 'notes', 'activities']);

        return Inertia::render('radpack-crm::Contacts/Show', [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name(),
                'email' => $contact->email,
                'phone' => $contact->phone,
                'status' => $contact->status,
                'status_label' => Presenter::optionLabel(Contact::blueprint(), 'status', $contact->status),
                'avatar' => $contact->avatarUrl(160),
                'initials' => Presenter::initials($contact->name()),
                'company' => $contact->company ? ['name' => $contact->company->name, 'url' => cp_route('radpack-crm.companies.show', $contact->company)] : null,
                'owner' => $contact->owner()?->name(),
                'aliases' => $contact->aliases->pluck('email'),
                'tags' => $contact->tags->pluck('name'),
                'created_at' => $contact->created_at?->toIso8601String(),
                'last_contacted_at' => $contact->last_contacted_at?->toIso8601String(),
            ],
            'details' => Presenter::details(Contact::blueprint(), $contact->blueprintValues(), ['first_name', 'last_name', 'email', 'phone', 'status', 'company', 'owner', 'tags', 'aliases']),
            'notes' => Presenter::notes($contact->notes),
            'sales' => Sales::for($contact),
            'activities' => Presenter::activities($contact->activities),
            'noteTypes' => Presenter::noteTypes(),
            'urls' => [
                'edit' => cp_route('radpack-crm.contacts.edit', $contact),
                'destroy' => cp_route('radpack-crm.contacts.destroy', $contact),
                'notes' => cp_route('radpack-crm.notes.store', ['contact', $contact->id]),
                'index' => cp_route('radpack-crm.contacts.index'),
            ],
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function edit(Contact $contact): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Contact::blueprint())
            ->icon('users')
            ->title($contact->name())
            ->values($contact->blueprintValues())
            ->submittingTo(cp_route('radpack-crm.contacts.update', $contact));
    }

    /**
     * @return array{redirect: string}
     */
    public function update(Request $request, Contact $contact): array
    {
        $this->authorize('edit crm');

        $contact->fillFromBlueprint(PublishForm::make(Contact::blueprint())->submit($request->all()))->save();

        return ['redirect' => cp_route('radpack-crm.contacts.show', $contact)];
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete crm');

        $contact->delete();

        return redirect()->route('statamic.cp.radpack-crm.contacts.index');
    }

    private function canEdit(): bool
    {
        return User::current()?->can('edit crm') ?? false;
    }
}
