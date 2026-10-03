<?php

namespace RadThemes\AlpCrm\Scopes;

use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use Statamic\Query\Scopes\Filter;

class CrmStatus extends Filter
{
    protected static $handle = 'crm_status';

    public $pinned = true;

    public static function title()
    {
        return __('Status');
    }

    public function fieldItems()
    {
        return [
            'status' => [
                'type' => 'checkboxes',
                'options' => $this->model()::blueprint()->field('status')?->get('options') ?? [],
            ],
        ];
    }

    public function apply($query, $values)
    {
        $query->whereIn('status', (array) $values['status']);
    }

    public function badge($values)
    {
        $options = $this->model()::blueprint()->field('status')?->get('options') ?? [];

        return __('Status').': '.collect((array) $values['status'])->map(fn ($status) => $options[$status] ?? $status)->implode(', ');
    }

    public function visibleTo($key)
    {
        return in_array($key, ['alp-crm.contacts', 'alp-crm.companies'], true);
    }

    /**
     * @return class-string<Contact|Company>
     */
    private function model(): string
    {
        return ($this->context['model'] ?? null) === 'company' ? Company::class : Contact::class;
    }
}
