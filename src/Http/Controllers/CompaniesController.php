<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Http\Resources\CompanyResource;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Support\Attachments;
use RadThemes\RadpackCrm\Support\ListingColumns;
use RadThemes\RadpackCrm\Support\Presenter;
use RadThemes\RadpackCrm\Support\Sales;
use RadThemes\RadpackCrm\Support\Tasks;
use Statamic\CP\PublishForm;
use Statamic\Facades\Scope;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use Statamic\Statamic;

class CompaniesController extends CpController
{
    use QueriesFilters;

    private const SORTABLE = ['name', 'email', 'status', 'created_at', 'contacts_count'];

    public function index(): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Companies/Index', [
            'filters' => Scope::filters('radpack-crm.companies', ['model' => 'company']),
            'columns' => ListingColumns::for('companies'),
            'jsonUrl' => cp_route('radpack-crm.companies.json'),
            'actionUrl' => cp_route('radpack-crm.companies.actions.run'),
            'createUrl' => cp_route('radpack-crm.companies.create'),
            'importUrl' => cp_route('radpack-crm.import.create', ['type' => 'companies']),
            'exportUrl' => cp_route('radpack-crm.export', ['type' => 'companies']),
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function json(FilteredRequest $request): JsonResponse
    {
        $this->authorize('view crm');

        $query = Company::query()->with('tags')->withCount('contacts');

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $badges = $this->queryFilters($query, $request->filters, ['model' => 'company']);

        $sort = in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'name';
        $query->orderBy($sort, $request->input('order') === 'desc' ? 'desc' : 'asc');

        return CompanyResource::collection($query->paginate(Statamic::cpPerPage($request->input('perPage'))))
            ->additional(['meta' => ['activeFilterBadges' => $badges, 'columns' => ListingColumns::fromRequest($request, 'companies')]])
            ->response();
    }

    public function create(): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Company::blueprint())
            ->icon('building-generic')
            ->title(__('Create Company'))
            ->values(['status' => 'lead'])
            ->submittingTo(cp_route('radpack-crm.companies.store'), 'POST');
    }

    /**
     * @return array{redirect: string}
     */
    public function store(Request $request): array
    {
        $this->authorize('edit crm');

        $company = (new Company)->fillFromBlueprint(PublishForm::make(Company::blueprint())->submit($request->all()));
        $company->save();

        return ['redirect' => cp_route('radpack-crm.companies.show', $company)];
    }

    public function show(Company $company): Response
    {
        $this->authorize('view crm');

        $company->load(['tags', 'contacts', 'notes', 'activities']);

        return Inertia::render('radpack-crm::Companies/Show', Attachments::for($company) + [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'website' => $company->website,
                'status' => $company->status,
                'status_label' => Presenter::optionLabel(Company::blueprint(), 'status', $company->status),
                'initials' => Presenter::initials($company->name),
                'owner' => $company->owner()?->name(),
                'tags' => $company->tags->pluck('name'),
                'created_at' => $company->created_at?->toIso8601String(),
            ],
            'contacts' => $company->contacts->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name(),
                'email' => $contact->email,
                'status_label' => Presenter::optionLabel(Contact::blueprint(), 'status', $contact->status),
                'url' => cp_route('radpack-crm.contacts.show', $contact),
            ]),
            'details' => Presenter::details(Company::blueprint(), $company->blueprintValues(), ['name', 'email', 'phone', 'website', 'status', 'owner', 'tags']),
            'notes' => Presenter::notes($company->notes),
            'sales' => Sales::for($company),
            'tasks' => $company->tasks()->with(['contact', 'company'])->limit(50)->get()->map(fn ($task) => Tasks::toArray($task)),
            'activities' => Presenter::activities($company->activities),
            'noteTypes' => Presenter::noteTypes(),
            'urls' => [
                'edit' => cp_route('radpack-crm.companies.edit', $company),
                'destroy' => cp_route('radpack-crm.companies.destroy', $company),
                'notes' => cp_route('radpack-crm.notes.store', ['company', $company->id]),
                'index' => cp_route('radpack-crm.companies.index'),
                'createTask' => cp_route('radpack-crm.tasks.create', ['company' => $company->id]),
                'createContact' => cp_route('radpack-crm.contacts.create'),
            ],
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function edit(Company $company): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(Company::blueprint())
            ->icon('building-generic')
            ->title($company->name)
            ->values($company->blueprintValues())
            ->submittingTo(cp_route('radpack-crm.companies.update', $company));
    }

    /**
     * @return array{redirect: string}
     */
    public function update(Request $request, Company $company): array
    {
        $this->authorize('edit crm');

        $company->fillFromBlueprint(PublishForm::make(Company::blueprint())->submit($request->all()))->save();

        return ['redirect' => cp_route('radpack-crm.companies.show', $company)];
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete crm');

        $company->delete();

        return redirect()->route('statamic.cp.radpack-crm.companies.index');
    }
}
