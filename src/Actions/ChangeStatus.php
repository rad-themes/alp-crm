<?php

namespace RadThemes\AlpCrm\Actions;

use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use Statamic\Actions\Action;

class ChangeStatus extends Action
{
    protected static $handle = 'crm_change_status';

    public $icon = 'taxonomies';

    public static function title()
    {
        return __('Change status');
    }

    public function visibleTo($item)
    {
        return $item instanceof Contact || $item instanceof Company;
    }

    public function visibleToBulk($items)
    {
        return $items->every(fn ($item) => $item instanceof Contact) || $items->every(fn ($item) => $item instanceof Company);
    }

    public function authorize($user, $item)
    {
        return $user->can('edit crm');
    }

    public function run($items, $values)
    {
        $items->each(fn ($item) => $item->update(['status' => $values['status']]));

        return trans_choice('Updated :count item|Updated :count items', $items->count());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function fieldItems()
    {
        $model = ($this->items?->first() instanceof Company) ? Company::class : Contact::class;

        return [
            'status' => [
                'type' => 'select',
                'display' => __('Status'),
                'options' => $model::blueprint()->field('status')?->get('options') ?? [],
                'validate' => 'required',
            ],
        ];
    }
}
