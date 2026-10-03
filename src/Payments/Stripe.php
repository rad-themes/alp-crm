<?php

namespace RadThemes\AlpCrm\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RadThemes\AlpCrm\Capture\LeadCapture;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Transaction;
use RadThemes\AlpCrm\Support\Documents;
use RadThemes\AlpCrm\Support\Money;
use RadThemes\AlpCrm\Support\Settings;
use RadThemes\AlpCrm\Support\TokenStore;
use RuntimeException;

/**
 * Stripe Checkout for invoices, signed webhooks, and importing Stripe charges as transactions.
 */
class Stripe
{
    public static function configured(): bool
    {
        return (bool) Settings::secret('stripe_secret_key');
    }

    private static function api(): PendingRequest
    {
        return Http::withToken((string) Settings::secret('stripe_secret_key'))->asForm()->baseUrl('https://api.stripe.com/v1')->timeout(20);
    }

    public static function toMinor(float $amount, string $currency): int
    {
        return (int) round(Payments::zeroDecimal($currency) ? $amount : $amount * 100);
    }

    public static function fromMinor(int|float $amount, string $currency): float
    {
        return Payments::zeroDecimal($currency) ? (float) $amount : Money::round($amount / 100);
    }

    /**
     * A Checkout session for the invoice's balance; returns the URL to send the client to.
     */
    public static function checkoutUrl(Invoice $invoice): string
    {
        $response = self::api()->post('checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $invoice->token,
            'customer_email' => $invoice->clientEmail(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($invoice->currency),
                    'unit_amount' => self::toMinor($invoice->balance(), $invoice->currency),
                    'product_data' => ['name' => __('Invoice :number', ['number' => $invoice->number]).($invoice->title ? ' — '.$invoice->title : '')],
                ],
            ]],
            'metadata' => ['alp_invoice' => $invoice->token],
            'payment_intent_data' => ['metadata' => ['alp_invoice' => $invoice->token]],
            'success_url' => route('statamic.alp-crm.public.invoice.paid', ['token' => $invoice->token, 'gateway' => 'stripe']).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => Documents::publicUrl($invoice),
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'Stripe error');
        }

        return (string) $response->json('url');
    }

    /**
     * Record a completed Checkout session (from the return URL or the webhook).
     */
    public static function completeSession(string $sessionId): ?Invoice
    {
        $session = self::api()->get("checkout/sessions/{$sessionId}")->throw()->json();

        return self::recordSession($session);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public static function recordSession(array $session): ?Invoice
    {
        $token = $session['metadata']['alp_invoice'] ?? $session['client_reference_id'] ?? null;
        $invoice = $token ? Invoice::where('token', $token)->first() : null;

        if ($invoice && ($session['payment_status'] ?? null) === 'paid') {
            Payments::record($invoice, self::fromMinor($session['amount_total'] ?? 0, $session['currency'] ?? $invoice->currency), 'stripe', (string) ($session['payment_intent'] ?? $session['id']));
        }

        return $invoice;
    }

    /**
     * Verify a webhook's Stripe-Signature header (t=…,v1=…) and return the event, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function verifyWebhook(string $payload, string $header, int $tolerance = 300): ?array
    {
        $secret = Settings::secret('stripe_webhook_secret');
        parse_str(str_replace(',', '&', $header), $parts);

        if (! $secret || ! isset($parts['t'], $parts['v1']) || abs(time() - (int) $parts['t']) > $tolerance) {
            return null;
        }

        $signatures = array_filter(array_map(fn ($part) => str_starts_with($part, 'v1=') ? substr($part, 3) : null, explode(',', $header)));
        $expected = hash_hmac('sha256', $parts['t'].'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return json_decode($payload, true);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function handleWebhook(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        match ($event['type'] ?? null) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => self::recordSession($object),
            'charge.succeeded', 'charge.refunded' => Payments::enabledForSync('stripe') ? self::importCharge($object) : null,
            default => null,
        };
    }

    /**
     * Import recent Stripe charges (and refunds) as transactions. Returns how many were added.
     */
    public static function sync(): int
    {
        $after = (int) TokenStore::get('stripe_sync_after', now()->subDays(30)->timestamp);
        $added = 0;
        $startingAfter = null;
        $newest = $after;

        do {
            $page = self::api()->get('charges', array_filter([
                'limit' => 100,
                'created[gt]' => $after,
                'starting_after' => $startingAfter,
                'expand[]' => 'data.balance_transaction',
            ]))->throw()->json();

            foreach ($page['data'] ?? [] as $charge) {
                $added += self::importCharge($charge);
                $newest = max($newest, (int) $charge['created']);
                $startingAfter = $charge['id'];
            }
        } while ($page['has_more'] ?? false);

        TokenStore::put('stripe_sync_after', $newest);

        return $added;
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    public static function importCharge(array $charge): int
    {
        if (($charge['status'] ?? null) !== 'succeeded' || ! ($charge['paid'] ?? false)) {
            return 0;
        }

        $currency = strtoupper((string) $charge['currency']);
        $externalId = (string) ($charge['payment_intent'] ?? $charge['id']);
        $email = $charge['billing_details']['email'] ?? $charge['receipt_email'] ?? null;
        $contact = $email ? (Contact::findByEmail($email) ?? (Settings::get('sync_create_contacts', true) ? LeadCapture::upsert(['email' => $email] + self::names($charge['billing_details']['name'] ?? null), 'customer') : null)) : null;
        $added = 0;

        if (! Transaction::where('source', 'stripe')->where('external_id', $externalId)->exists()) {
            $invoice = ($token = $charge['metadata']['alp_invoice'] ?? null) ? Invoice::where('token', $token)->first() : null;
            $fee = is_array($charge['balance_transaction'] ?? null) ? self::fromMinor($charge['balance_transaction']['fee'] ?? 0, $currency) : 0;

            $invoice
                ? Payments::record($invoice, self::fromMinor($charge['amount'], $currency), 'stripe', $externalId, $fee)
                : Transaction::create([
                    'title' => $charge['description'] ?: __('Stripe payment'),
                    'reference' => $charge['id'],
                    'type' => 'sale',
                    'status' => 'succeeded',
                    'amount' => self::fromMinor($charge['amount'], $currency),
                    'fee' => $fee,
                    'currency' => $currency,
                    'date' => date('Y-m-d', (int) $charge['created']),
                    'contact_id' => $contact?->id,
                    'source' => 'stripe',
                    'external_id' => $externalId,
                ]);
            $added++;
        }

        $refunded = (int) ($charge['amount_refunded'] ?? 0);
        $refundId = 'refund:'.$charge['id'];
        $existing = Transaction::where('source', 'stripe')->where('external_id', $refundId)->first();

        if ($refunded > 0 && (! $existing || (float) $existing->amount !== self::fromMinor($refunded, $currency))) {
            $sale = Transaction::where('source', 'stripe')->where('external_id', $externalId)->first();
            Transaction::updateOrCreate(['source' => 'stripe', 'external_id' => $refundId], [
                'title' => __('Refund: :title', ['title' => $sale?->title ?? $charge['id']]),
                'reference' => $charge['id'],
                'type' => 'refund',
                'status' => 'succeeded',
                'amount' => self::fromMinor($refunded, $currency),
                'currency' => $currency,
                'date' => today(),
                'contact_id' => $sale?->contact_id ?? $contact?->id,
                'invoice_id' => $sale?->invoice_id,
            ]);
            $added++;
        }

        return $added;
    }

    /**
     * @return array{first_name?: string, last_name?: ?string}
     */
    public static function names(?string $name): array
    {
        if (! $name) {
            return [];
        }

        [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, null);

        return ['first_name' => $first, 'last_name' => $last];
    }
}
