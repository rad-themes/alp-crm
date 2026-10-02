<?php

namespace RadThemes\RadpackCrm\Support;

use Illuminate\Database\Eloquent\Model;

class Numbering
{
    /**
     * The next sequential number for a prefix, e.g. INV-0042.
     *
     * @param  class-string<Model>  $model
     */
    public static function next(string $model, string $prefix): string
    {
        $highest = $model::query()
            ->where('number', 'like', str_replace(['%', '_'], ['\%', '\_'], $prefix).'%')
            ->pluck('number')
            ->map(fn (string $number) => (int) preg_replace('/\D/', '', substr($number, strlen($prefix))))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
