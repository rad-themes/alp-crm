<?php

namespace RadThemes\RadpackCrm\Support;

use Statamic\Facades\Addon;
use Statamic\Facades\Asset;
use Statamic\Facades\Dictionary;

class Settings
{
    private const DEFAULTS = [
        'currency' => 'USD',
        'invoice_prefix' => 'INV-',
        'quote_prefix' => 'QUO-',
        'payment_terms_days' => 30,
        'quote_valid_days' => 30,
        'tax_rates' => [],
        'prices_include_tax' => false,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Addon::get('rad-themes/radpack-crm')->setting($key);

        if ($value === null || $value === '' || $value === []) {
            return $default ?? self::DEFAULTS[$key] ?? null;
        }

        return $value;
    }

    public static function currency(): string
    {
        return strtoupper((string) collect(self::get('currency'))->first() ?: 'USD');
    }

    /**
     * @return array<string, string> code => label
     */
    public static function currencyOptions(): array
    {
        return Dictionary::find('currencies')->options();
    }

    /**
     * @return array<int, array{name: string, rate: float}>
     */
    public static function taxRates(): array
    {
        return collect((array) self::get('tax_rates'))
            ->map(fn ($row) => ['name' => (string) ($row['name'] ?? ''), 'rate' => (float) ($row['rate'] ?? 0)])
            ->filter(fn ($row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{name: ?string, address: ?string, email: ?string, phone: ?string, tax_number: ?string, logo: ?string}
     */
    public static function business(): array
    {
        $logo = collect(self::get('business_logo'))->first();

        return [
            'name' => self::get('business_name') ?: config('app.name'),
            'address' => self::get('business_address'),
            'email' => self::get('business_email'),
            'phone' => self::get('business_phone'),
            'tax_number' => self::get('business_tax_number'),
            'logo' => $logo ? Asset::find(str_contains($logo, '::') ? $logo : "assets::{$logo}")?->absoluteUrl() : null,
        ];
    }
}
