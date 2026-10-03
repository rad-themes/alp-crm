<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Statamic\Facades\User;

/**
 * @property int $id
 * @property string $event
 * @property string $description
 * @property ?array<string, mixed> $properties
 * @property ?string $user_id
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'crm_activities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): ?\Statamic\Contracts\Auth\User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }
}
