<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use RadThemes\RadpackCrm\Models\Contact;
use Statamic\Http\Controllers\CP\ActionController;

class ContactActionController extends ActionController
{
    protected static $key = 'radpack-crm.contacts';

    protected function getSelectedItems($items, $context)
    {
        return Contact::query()->whereIn('id', $items->all())->get();
    }
}
