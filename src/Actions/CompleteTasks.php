<?php

namespace RadThemes\AlpCrm\Actions;

use RadThemes\AlpCrm\Models\Task;
use Statamic\Actions\Action;

class CompleteTasks extends Action
{
    protected static $handle = 'crm_complete_tasks';

    public $icon = 'checkmark';

    public static function title()
    {
        return __('Mark complete');
    }

    public function visibleTo($item)
    {
        return $item instanceof Task && ! $item->isDone();
    }

    public function authorize($user, $item)
    {
        return $user->can('edit crm');
    }

    public function run($items, $values)
    {
        $items->each->complete();

        return trans_choice('Completed :count task|Completed :count tasks', $items->count());
    }
}
