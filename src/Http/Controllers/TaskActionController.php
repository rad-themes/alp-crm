<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use RadThemes\RadpackCrm\Models\Task;
use Statamic\Http\Controllers\CP\ActionController;

class TaskActionController extends ActionController
{
    protected static $key = 'radpack-crm.tasks';

    protected function getSelectedItems($items, $context)
    {
        return Task::query()->whereIn('id', $items->all())->get();
    }
}
