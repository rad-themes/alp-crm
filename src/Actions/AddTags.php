<?php

namespace RadThemes\AlpCrm\Actions;

use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use Statamic\Actions\Action;

class AddTags extends Action
{
    protected static $handle = 'crm_add_tags';

    public $icon = 'add-tag';

    public static function title()
    {
        return __('Add tags');
    }

    public function visibleTo($item)
    {
        return $item instanceof Contact || $item instanceof Company;
    }

    public function authorize($user, $item)
    {
        return $user->can('edit crm');
    }

    public function run($items, $values)
    {
        $items->each->attachTags((array) $values['tags']);

        return trans_choice('Tagged :count item|Tagged :count items', $items->count());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function fieldItems()
    {
        return [
            'tags' => [
                'type' => 'taggable',
                'display' => __('Tags'),
                'validate' => 'required',
            ],
        ];
    }
}
