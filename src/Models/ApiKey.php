<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A REST API key. Only a SHA-256 hash is stored; the key itself is shown once.
 *
 * @property int $id
 * @property string $name
 * @property bool $can_write
 */
class ApiKey extends Model
{
    protected $table = 'crm_api_keys';

    protected $guarded = ['id'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['can_write' => 'boolean', 'last_used_at' => 'datetime'];
    }

    /**
     * @return array{0: ApiKey, 1: string} the saved key and its plain-text secret
     */
    public static function generate(string $name, bool $canWrite = true, ?string $userId = null): array
    {
        $plain = 'rpk_'.Str::random(40);

        $key = static::create([
            'name' => $name,
            'key_hash' => hash('sha256', $plain),
            'hint' => substr($plain, -4),
            'can_write' => $canWrite,
            'user_id' => $userId,
        ]);

        return [$key, $plain];
    }

    public static function findByPlainKey(string $plain): ?self
    {
        return $plain === '' ? null : static::where('key_hash', hash('sha256', $plain))->first();
    }
}
