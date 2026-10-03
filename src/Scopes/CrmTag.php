<?php

namespace RadThemes\AlpCrm\Scopes;

use RadThemes\AlpCrm\Models\Tag;
use Statamic\Query\Scopes\Filter;

class CrmTag extends Filter
{
    protected static $handle = 'crm_tag';

    public $pinned = true;

    public static function title()
    {
        return __('Tag');
    }

    public function fieldItems()
    {
        return [
            'tags' => [
                'type' => 'select',
                'multiple' => true,
                'options' => Tag::query()->orderBy('name')->pluck('name', 'slug')->all(),
            ],
        ];
    }

    public function apply($query, $values)
    {
        $query->whereHas('tags', fn ($tags) => $tags->whereIn('slug', (array) $values['tags']));
    }

    public function badge($values)
    {
        return __('Tag').': '.Tag::query()->whereIn('slug', (array) $values['tags'])->pluck('name')->implode(', ');
    }

    public function visibleTo($key)
    {
        return in_array($key, ['alp-crm.contacts', 'alp-crm.companies'], true);
    }
}
