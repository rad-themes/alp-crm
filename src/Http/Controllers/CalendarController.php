<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Support\Tasks;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * A month calendar of tasks, plus invoice due dates.
 */
class CalendarController extends CpController
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('view crm');

        $month = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth(), now()->startOfMonth(), false);
        $from = $month->copy()->startOfWeek();
        $to = $month->copy()->endOfMonth()->endOfWeek();
        $mine = $request->boolean('mine');

        $tasks = Task::query()->with(['contact', 'company'])
            ->between($from, $to)
            ->when($mine, fn ($query) => $query->where('assigned_to', User::current()->id()))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Task $task) => Tasks::toArray($task) + ['date' => $task->starts_at->toDateString(), 'kind' => 'task']);

        $invoices = Invoice::query()->outstanding()
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => 'invoice-'.$invoice->id,
                'kind' => 'invoice',
                'title' => __(':number due', ['number' => $invoice->number]),
                'date' => $invoice->due_date->toDateString(),
                'overdue' => $invoice->isOverdue(),
                'edit_url' => cp_route('radpack-crm.invoices.show', $invoice),
            ]);

        return Inertia::render('radpack-crm::Calendar', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->isoFormat('MMMM YYYY'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => today()->toDateString(),
            'weekStartsOn' => $from->dayOfWeek,
            'events' => $tasks->concat($invoices)->groupBy('date'),
            'urls' => [
                'previous' => cp_route('radpack-crm.calendar', array_filter(['month' => $month->copy()->subMonth()->format('Y-m'), 'mine' => $mine ?: null])),
                'next' => cp_route('radpack-crm.calendar', array_filter(['month' => $month->copy()->addMonth()->format('Y-m'), 'mine' => $mine ?: null])),
                'today' => cp_route('radpack-crm.calendar', array_filter(['mine' => $mine ?: null])),
                'toggleMine' => cp_route('radpack-crm.calendar', array_filter(['month' => $month->format('Y-m'), 'mine' => $mine ? null : 1])),
                'create' => cp_route('radpack-crm.tasks.create'),
                'tasks' => cp_route('radpack-crm.tasks.index'),
            ],
            'mine' => $mine,
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }
}
