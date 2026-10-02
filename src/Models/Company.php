<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RadThemes\RadpackCrm\Database\Factories\CompanyFactory;
use RadThemes\RadpackCrm\Models\Concerns\HasBlueprint;
use RadThemes\RadpackCrm\Models\Concerns\HasTags;
use RadThemes\RadpackCrm\Models\Concerns\LogsActivity;
use Statamic\Facades\User;

/**
 * @property int $id
 * @property string $name
 * @property string $status
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $website
 * @property ?string $owner_id
 * @property ?array<string, mixed> $data
 */
class Company extends Model
{
    use HasBlueprint, HasFactory, HasTags, LogsActivity;

    protected $table = 'crm_companies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (Company $company) {
            $company->contacts()->update(['company_id' => null]);
            $company->notes()->delete();
        });
    }

    public static function blueprintHandle(): string
    {
        return 'company';
    }

    protected function blueprintColumns(): array
    {
        return ['name', 'status', 'email', 'phone', 'website'];
    }

    protected function relationBlueprintHandles(): array
    {
        return ['owner', 'tags'];
    }

    protected function relationBlueprintValues(): array
    {
        return [
            'owner' => $this->owner_id ? [$this->owner_id] : [],
            'tags' => $this->tags->pluck('name')->all(),
        ];
    }

    protected function fillRelationsFromBlueprint(array $values): void
    {
        if (array_key_exists('owner', $values)) {
            $this->owner_id = collect($values['owner'])->first();
        }

        if (array_key_exists('tags', $values)) {
            $this->setTagsLater((array) $values['tags']);
        }
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest()->latest('id');
    }

    public function owner(): ?\Statamic\Contracts\Auth\User
    {
        return $this->owner_id ? User::find($this->owner_id) : null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function activityLabel(): string
    {
        return __('Company');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('website', 'like', $like));
    }
}
