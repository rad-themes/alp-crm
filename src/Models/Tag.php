<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Tag extends Model
{
    protected $table = 'crm_tags';

    protected $guarded = ['id'];

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, Tag>
     */
    public static function findOrCreateMany(array $names): Collection
    {
        return new Collection(collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => Str::slug($name))
            ->map(fn ($name) => static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]))
            ->values()
            ->all());
    }

    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable', 'crm_taggables');
    }

    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'taggable', 'crm_taggables');
    }
}
