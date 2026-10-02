<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Statamic\Facades\User;

/**
 * A note, call, meeting or email logged against a contact or company.
 *
 * @property int $id
 * @property string $type
 * @property string $body
 * @property ?string $user_id
 */
class Note extends Model
{
    public const TYPES = ['note', 'call', 'meeting', 'email', 'sms'];

    protected $table = 'crm_notes';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::created(function (Note $note) {
            if (in_array($note->type, ['call', 'meeting', 'email'], true) && $note->notable instanceof Contact) {
                $note->notable->forceFill(['last_contacted_at' => $note->created_at])->saveQuietly();
            }
        });
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): ?\Statamic\Contracts\Auth\User
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }
}
