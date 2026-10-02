<?php

namespace RadThemes\RadpackCrm\Models\Concerns;

use RadThemes\RadpackCrm\Events\CrmEvent;

/**
 * Fires "<type>.created", "<type>.updated" and "<type>.deleted" CRM events.
 */
trait FiresCrmEvents
{
    abstract public static function crmEventType(): string;

    /**
     * @return array<int, string>
     */
    protected static function crmEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    public static function bootFiresCrmEvents(): void
    {
        $type = static::crmEventType();
        $events = static::crmEvents();

        if (in_array('created', $events, true)) {
            static::created(function ($model) use ($type) {
                CrmEvent::fire("{$type}.created", $model);
            });
        }

        if (in_array('updated', $events, true)) {
            static::updated(function ($model) use ($type) {
                if (array_diff(array_keys($model->getChanges()), ['updated_at'])) {
                    CrmEvent::fire("{$type}.updated", $model);
                }
            });
        }

        if (in_array('deleted', $events, true)) {
            static::deleted(function ($model) use ($type) {
                CrmEvent::fire("{$type}.deleted", $model);
            });
        }
    }
}
