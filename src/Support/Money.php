<?php

namespace RadThemes\AlpCrm\Support;

use NumberFormatter;

class Money
{
    /**
     * "$1,234.50" in the app's locale.
     */
    public static function format(float|int|string|null $amount, string $currency): string
    {
        $amount = (float) $amount;

        if (class_exists(NumberFormatter::class)) {
            $formatted = (new NumberFormatter(app()->getLocale(), NumberFormatter::CURRENCY))->formatCurrency($amount, strtoupper($currency));

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return strtoupper($currency).' '.number_format($amount, 2);
    }

    public static function round(float $amount): float
    {
        return round($amount + 0.0, 2);
    }
}
