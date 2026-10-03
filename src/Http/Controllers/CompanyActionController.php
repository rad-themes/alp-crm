<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use RadThemes\AlpCrm\Models\Company;
use Statamic\Http\Controllers\CP\ActionController;

class CompanyActionController extends ActionController
{
    protected static $key = 'alp-crm.companies';

    protected function getSelectedItems($items, $context)
    {
        return Company::query()->whereIn('id', $items->all())->get();
    }
}
