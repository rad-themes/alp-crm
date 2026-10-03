<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use RadThemes\AlpCrm\Models\Contact;
use Statamic\Http\Controllers\CP\ActionController;

class ContactActionController extends ActionController
{
    protected static $key = 'alp-crm.contacts';

    protected function getSelectedItems($items, $context)
    {
        return Contact::query()->whereIn('id', $items->all())->get();
    }
}
