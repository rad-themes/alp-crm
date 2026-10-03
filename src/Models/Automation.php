<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "When <trigger>, if <conditions>, do <actions>" — each action can wait first.
 *
 * @property int $id
 * @property string $name
 * @property bool $active
 * @property string $trigger a CrmEvent name
 * @property array<string, mixed>|null $trigger_options e.g. ['tag' => 'VIP'], ['status' => 'customer'], ['form' => 'contact']
 * @property string $match all|any
 * @property array<int, array<string, mixed>>|null $conditions segment rules the contact must match
 * @property array<int, array<string, mixed>> $actions [['type' => 'add_tag', 'delay' => 0, 'delay_unit' => 'days', ...]]
 */
class Automation extends Model
{
    protected $table = 'crm_automations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'trigger_options' => 'array',
            'conditions' => 'array',
            'actions' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /**
     * Seconds to wait before an action.
     *
     * @param  array<string, mixed>  $action
     */
    public static function delaySeconds(array $action): int
    {
        $amount = max(0, (int) ($action['delay'] ?? 0));

        return $amount * match ($action['delay_unit'] ?? 'minutes') {
            'days' => 86400,
            'hours' => 3600,
            default => 60,
        };
    }
}
