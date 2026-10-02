<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use RadThemes\RadpackCrm\Models\Company;
use Statamic\Http\Controllers\CP\ActionController;

class CompanyActionController extends ActionController
{
    protected static $key = 'radpack-crm.companies';

    protected function getSelectedItems($items, $context)
    {
        return Company::query()->whereIn('id', $items->all())->get();
    }
}
