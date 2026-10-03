<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra email address for a contact ("AKA mode").
 *
 * @property int $id
 * @property string $email
 */
class ContactAlias extends Model
{
    protected $table = 'crm_contact_aliases';

    protected $guarded = ['id'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
