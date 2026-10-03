<?php

namespace RadThemes\AlpCrm\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Tag;

trait HasTags
{
    /**
     * Tag names to sync once the model has been saved.
     *
     * @var array<int, string>|null
     */
    protected ?array $pendingTags = null;

    public static function bootHasTags(): void
    {
        static::deleting(function ($model) {
            $model->tags()->detach();
        });

        static::saved(function ($model) {
            if ($model->pendingTags !== null) {
                $model->syncTags($model->pendingTags);
                $model->pendingTags = null;
            }
        });
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'crm_taggables')->orderBy('name');
    }

    /**
     * @param  array<int, string>  $names
     */
    public function syncTags(array $names): void
    {
        $this->fireTagged($this->tags()->sync(Tag::findOrCreateMany($names)->pluck('id'))['attached']);
        $this->unsetRelation('tags');
    }

    /**
     * @param  array<int, string>  $names
     */
    public function attachTags(array $names): void
    {
        $this->fireTagged($this->tags()->syncWithoutDetaching(Tag::findOrCreateMany($names)->pluck('id'))['attached']);
        $this->unsetRelation('tags');
    }

    /**
     * @param  array<int, int>  $tagIds  newly attached tags
     */
    protected function fireTagged(array $tagIds): void
    {
        if ($tagIds && $this instanceof Contact) {
            CrmEvent::fire('contact.tagged', $this, ['tags' => Tag::whereIn('id', $tagIds)->pluck('name')->all()]);
        }
    }

    /**
     * @param  array<int, string>  $names
     */
    public function setTagsLater(array $names): void
    {
        $this->pendingTags = array_values(array_filter(array_map('trim', $names)));
    }

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereHas('tags', fn (Builder $tags) => $tags->where('slug', Str::slug($tag)));
    }
}
