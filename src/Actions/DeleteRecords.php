<?php

namespace RadThemes\RadpackCrm\Actions;

use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use Statamic\Actions\Action;

class DeleteRecords extends Action
{
    protected static $handle = 'crm_delete';

    public $icon = 'trash';

    protected $dangerous = true;

    public static function title()
    {
        return __('Delete');
    }

    public function visibleTo($item)
    {
        return $item instanceof Contact || $item instanceof Company;
    }

    public function authorize($user, $item)
    {
        return $user->can('delete crm');
    }

    public function confirmationText()
    {
        return __('Are you sure you want to delete this?|Are you sure you want to delete these :count items?');
    }

    public function buttonText()
    {
        return __('Delete|Delete :count items');
    }

    public function run($items, $values)
    {
        $items->each->delete();

        return trans_choice('Deleted :count item|Deleted :count items', $items->count());
    }
}
