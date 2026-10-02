<?php

namespace RadThemes\RadpackCrm\Payments;

use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * Online invoice payments.
 */
class Payments
{
    /**
     * Gateways that are set up, e.g. ['stripe' => 'Pay by card', 'paypal' => 'Pay with PayPal'].
     *
     * @return array<string, string>
     */
    public static function gateways(): array
    {
        return array_filter([
            'stripe' => Stripe::configured() ? __('Pay by card') : null,
            'paypal' => PayPal::configured() ? __('Pay with PayPal') : null,
        ]);
    }

    /**
     * Record a payment once, however many times the gateway tells us about it.
     */
    public static function record(Invoice $invoice, float $amount, string $source, string $externalId, ?float $fee = null): ?Transaction
    {
        if (Transaction::where('source', $source)->where('external_id', $externalId)->exists()) {
            return null;
        }

        return $invoice->recordPayment($amount, [
            'source' => $source,
            'external_id' => $externalId,
            'reference' => $externalId,
            'fee' => $fee ?? 0,
        ]);
    }

    /**
     * Minor units per major unit for a currency (Stripe/PayPal amounts).
     */
    public static function zeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF', 'HUF', 'TWD'], true);
    }

    public static function enabledForSync(string $gateway): bool
    {
        return (bool) Settings::get("{$gateway}_sync", false);
    }
}
