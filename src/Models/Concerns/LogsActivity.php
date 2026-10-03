<?php

namespace RadThemes\AlpCrm\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RadThemes\AlpCrm\Models\Activity;
use Statamic\Facades\User;

/**
 * Records created / status-changed / deleted events in the activity log.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('created', __(':type created', ['type' => $model->activityLabel()]));
        });

        static::updated(function ($model) {
            if ($model->wasChanged('status')) {
                $model->logActivity('status_changed', __('Status changed: :from → :to', [
                    'from' => $model->statusLabel($model->getOriginal('status')),
                    'to' => $model->statusLabel($model->status),
                ]), ['from' => $model->getOriginal('status'), 'to' => $model->status]);
            }
        });

        // Model event listeners must not return a value: a non-null return stops later "deleting" listeners.
        static::deleting(function ($model) {
            $model->activities()->delete();
        });
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('created_at')->latest('id');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function logActivity(string $event, string $description, array $properties = []): Activity
    {
        return $this->activities()->create([
            'event' => $event,
            'description' => $description,
            'properties' => $properties ?: null,
            'user_id' => User::current()?->id(),
        ]);
    }

    abstract public function activityLabel(): string;

    abstract public function statusLabel(?string $status): string;
}
