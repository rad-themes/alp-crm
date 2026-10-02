<?php

namespace RadThemes\RadpackCrm\Fieldtypes;

use RadThemes\RadpackCrm\Models\Contact;
use Statamic\CP\Column;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Relationship;
use Statamic\Statamic;

/**
 * Pick CRM contacts, with the same picker UI as Statamic's entries field.
 */
class CrmContacts extends Relationship
{
    protected static $handle = 'crm_contacts';

    protected $icon = 'users';

    protected $canEdit = false;

    protected $canCreate = false;

    protected $categories = ['relationship'];

    protected function toItemArray($id)
    {
        $contact = Contact::find($id);

        return $contact
            ? ['id' => $contact->id, 'title' => $contact->name(), 'edit_url' => cp_route('radpack-crm.contacts.show', $contact)]
            : $this->invalidItemArray($id);
    }

    public function getIndexItems($request)
    {
        if (! User::current()->can('view crm')) {
            return collect();
        }

        $query = Contact::query()
            ->with('company')
            ->when($request->search, fn ($query, $search) => $query->search($search))
            ->when($request->exclusions, fn ($query, $exclusions) => $query->whereNotIn('id', $exclusions))
            ->orderBy('first_name')->orderBy('last_name');

        $toItem = fn (Contact $contact) => [
            'id' => $contact->id,
            'title' => $contact->name(),
            'email' => $contact->email,
            'company' => $contact->company?->name,
        ];

        if ($request->boolean('paginate', true)) {
            $contacts = $query->paginate($request->filled('perPage') ? Statamic::cpPerPage($request->integer('perPage')) : 15);
            $contacts->getCollection()->transform($toItem);

            return $contacts;
        }

        return $query->get()->map($toItem);
    }

    protected function getColumns()
    {
        return [
            Column::make('title')->label(__('Name')),
            Column::make('email')->label(__('Email')),
            Column::make('company')->label(__('Company')),
        ];
    }

    protected function augmentValue($value)
    {
        return Contact::find($value);
    }
}
