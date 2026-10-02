<?php

namespace RadThemes\RadpackCrm\Fieldtypes;

use RadThemes\RadpackCrm\Models\Company;
use Statamic\CP\Column;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Relationship;
use Statamic\Statamic;

/**
 * Pick CRM companies, with the same picker UI as Statamic's entries field.
 */
class CrmCompanies extends Relationship
{
    protected static $handle = 'crm_companies';

    protected $icon = 'building-generic';

    protected $canEdit = false;

    protected $canCreate = false;

    protected $categories = ['relationship'];

    protected function toItemArray($id)
    {
        $company = Company::find($id);

        return $company
            ? ['id' => $company->id, 'title' => $company->name, 'edit_url' => cp_route('radpack-crm.companies.show', $company)]
            : $this->invalidItemArray($id);
    }

    public function getIndexItems($request)
    {
        if (! User::current()->can('view crm')) {
            return collect();
        }

        $query = Company::query()
            ->when($request->search, fn ($query, $search) => $query->search($search))
            ->when($request->exclusions, fn ($query, $exclusions) => $query->whereNotIn('id', $exclusions))
            ->orderBy('name');

        $toItem = fn (Company $company) => ['id' => $company->id, 'title' => $company->name, 'email' => $company->email];

        if ($request->boolean('paginate', true)) {
            $companies = $query->paginate($request->filled('perPage') ? Statamic::cpPerPage($request->integer('perPage')) : 15);
            $companies->getCollection()->transform($toItem);

            return $companies;
        }

        return $query->get()->map($toItem);
    }

    protected function getColumns()
    {
        return [Column::make('title')->label(__('Name')), Column::make('email')->label(__('Email'))];
    }

    protected function augmentValue($value)
    {
        return Company::find($value);
    }
}
