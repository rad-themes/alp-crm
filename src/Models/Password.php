<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credential kept for a client (hosting, CMS, domain registrar…), encrypted at rest with the app key.
 *
 * @property int $id
 * @property string $label
 * @property ?string $url
 * @property ?string $username
 * @property ?string $password
 * @property ?string $notes
 */
class Password extends Model
{
    protected $table = 'crm_passwords';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'notes', 'username'];

    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
