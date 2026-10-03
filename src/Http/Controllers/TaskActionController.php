<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use RadThemes\AlpCrm\Models\Task;
use Statamic\Http\Controllers\CP\ActionController;

class TaskActionController extends ActionController
{
    protected static $key = 'alp-crm.tasks';

    protected function getSelectedItems($items, $context)
    {
        return Task::query()->whereIn('id', $items->all())->get();
    }
}
