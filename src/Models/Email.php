<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Statamic\Facades\User;

/**
 * An email sent (or scheduled) to a single contact from the CRM.
 *
 * @property int $id
 * @property string $to
 * @property string $subject
 * @property string $body
 * @property string $status scheduled|sent|failed|cancelled
 */
class Email extends Model
{
    protected $table = 'crm_emails';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sender(): ?\Statamic\Contracts\Auth\User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }
}
