<?php

namespace RadThemes\RadpackCrm\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

/**
 * Small encrypted key/value store for OAuth tokens and sync cursors
 * (kept out of the cache, which can be cleared, and out of version-controlled settings).
 */
class TokenStore
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $all = self::all();
        $all[$key] = $value;
        self::write($all);
    }

    public static function forget(string $key): void
    {
        $all = self::all();
        unset($all[$key]);
        self::write($all);
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(): array
    {
        if (! is_file($path = self::path())) {
            return [];
        }

        try {
            return (array) json_decode(Crypt::decryptString((string) file_get_contents($path)), true);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $all
     */
    private static function write(array $all): void
    {
        File::ensureDirectoryExists(dirname(self::path()));
        file_put_contents(self::path(), Crypt::encryptString(json_encode($all)), LOCK_EX);
    }

    private static function path(): string
    {
        return storage_path('app/radpack-crm/tokens.enc');
    }
}
