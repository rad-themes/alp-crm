<?php

namespace RadThemes\AlpCrm\Scopes;

use RadThemes\AlpCrm\Models\Segment;
use Statamic\Query\Scopes\Filter;

class CrmSegment extends Filter
{
    protected static $handle = 'crm_segment';

    public $pinned = true;

    public static function title()
    {
        return __('Segment');
    }

    public function fieldItems()
    {
        return [
            'segment' => [
                'type' => 'select',
                'options' => Segment::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
        ];
    }

    public function apply($query, $values)
    {
        $segment = Segment::find($values['segment'] ?? null);

        $segment ? Segment::applyTo($query, (array) $segment->conditions, $segment->match) : $query->whereRaw('1 = 0');
    }

    public function badge($values)
    {
        return __('Segment').': '.Segment::find($values['segment'] ?? null)?->name;
    }

    public function visibleTo($key)
    {
        return $key === 'alp-crm.contacts';
    }

    /**
     * Contacts list URL pre-filtered to a segment.
     */
    public static function url(Segment $segment): string
    {
        return cp_route('alp-crm.contacts.index', [
            'filters' => base64_encode(json_encode([static::$handle => ['segment' => (string) $segment->id]])),
        ]);
    }
}
