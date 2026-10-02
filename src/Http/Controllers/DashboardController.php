<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Models\Activity;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Support\Money;
use RadThemes\RadpackCrm\Support\Presenter;
use RadThemes\RadpackCrm\Support\Settings;
use RadThemes\RadpackCrm\Support\Tasks;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class DashboardController extends CpController
{
    public function __invoke(): Response
    {
        $this->authorize('view crm');

        $statusOptions = Contact::blueprint()->field('status')?->get('options') ?? [];
        $byStatus = Contact::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('radpack-crm::Dashboard', [
            'crmName' => Settings::get('crm_name') ?: __('CRM'),
            'stats' => [
                ['label' => __('Contacts'), 'value' => Contact::count(), 'url' => cp_route('radpack-crm.contacts.index')],
                ['label' => __('Companies'), 'value' => Company::count(), 'url' => cp_route('radpack-crm.companies.index')],
                ['label' => __('New contacts this month'), 'value' => Contact::where('created_at', '>=', now()->startOfMonth())->count(), 'url' => null],
                ['label' => __('Revenue this month'), 'value' => Money::format(Transaction::revenue(Transaction::query()->where('currency', Settings::currency())->whereDate('date', '>=', now()->startOfMonth())), Settings::currency()), 'url' => cp_route('radpack-crm.transactions.index')],
                ['label' => __('Outstanding invoices'), 'value' => Money::format(Invoice::outstanding()->where('currency', Settings::currency())->get()->sum(fn (Invoice $invoice) => $invoice->balance()), Settings::currency()), 'url' => cp_route('radpack-crm.invoices.index')],
                ['label' => __('Overdue invoices'), 'value' => Invoice::overdue()->count(), 'url' => cp_route('radpack-crm.invoices.index')],
            ],
            'statuses' => collect($statusOptions)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
                'total' => (int) ($byStatus[$value] ?? 0),
            ])->values(),
            'recentContacts' => Contact::query()->with('company')->latest()->latest('id')->limit(6)->get()->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name(),
                'initials' => Presenter::initials($contact->name()),
                'company' => $contact->company?->name,
                'status_label' => Presenter::optionLabel(Contact::blueprint(), 'status', $contact->status),
                'url' => cp_route('radpack-crm.contacts.show', $contact),
            ]),
            'activity' => Activity::query()->with('subject')->latest('created_at')->latest('id')->limit(10)->get()
                ->filter(fn (Activity $activity) => $activity->subject !== null)
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'subject' => $activity->subject->name(),
                    'url' => Presenter::url($activity->subject),
                    'causer' => $activity->causer()?->name(),
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])->values(),
            'myTasks' => Task::query()->open()->with(['contact', 'company'])
                ->where('assigned_to', User::current()->id())
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->addDays(7)->endOfDay()))
                ->orderByRaw('starts_at is null')->orderBy('starts_at')->limit(8)->get()
                ->map(fn ($task) => Tasks::toArray($task)),
            'urls' => [
                'tasks' => cp_route('radpack-crm.tasks.index'),
                'createContact' => cp_route('radpack-crm.contacts.create'),
                'createCompany' => cp_route('radpack-crm.companies.create'),
            ],
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }
}
