<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One action of an automation, for one contact: pending until run_at, then done, skipped or failed.
 */
class AutomationRun extends Model
{
    protected $table = 'crm_automation_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['run_at' => 'datetime', 'context' => 'array'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
