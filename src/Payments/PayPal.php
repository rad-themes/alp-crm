<?php

namespace RadThemes\AlpCrm\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RadThemes\AlpCrm\Capture\LeadCapture;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Transaction;
use RadThemes\AlpCrm\Support\Documents;
use RadThemes\AlpCrm\Support\Settings;
use RadThemes\AlpCrm\Support\TokenStore;
use RuntimeException;

/**
 * PayPal Checkout (Orders v2) for invoices, and importing PayPal transactions.
 */
class PayPal
{
    public static function configured(): bool
    {
        return Settings::secret('paypal_client_id') && Settings::secret('paypal_secret');
    }

    private static function base(): string
    {
        return Settings::get('paypal_mode', 'live') === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private static function api(): PendingRequest
    {
        $token = Cache::remember('alp-crm.paypal-token.'.md5(self::base().Settings::secret('paypal_client_id')), 3000, fn () => Http::asForm()
            ->withBasicAuth((string) Settings::secret('paypal_client_id'), (string) Settings::secret('paypal_secret'))
            ->post(self::base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token'));

        return Http::withToken($token)->baseUrl(self::base())->acceptJson()->timeout(20);
    }

    public static function amount(float $amount, string $currency): string
    {
        return Payments::zeroDecimal($currency) ? (string) round($amount) : number_format($amount, 2, '.', '');
    }

    public static function checkoutUrl(Invoice $invoice): string
    {
        $response = self::api()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $invoice->number,
                'custom_id' => $invoice->token,
                'invoice_id' => $invoice->number.'-'.substr(md5($invoice->token.$invoice->balance()), 0, 6),
                'description' => mb_substr(__('Invoice :number', ['number' => $invoice->number]).($invoice->title ? ' — '.$invoice->title : ''), 0, 127),
                'amount' => ['currency_code' => strtoupper($invoice->currency), 'value' => self::amount($invoice->balance(), $invoice->currency)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => route('statamic.alp-crm.public.invoice.paid', ['token' => $invoice->token, 'gateway' => 'paypal']),
                'cancel_url' => Documents::publicUrl($invoice),
                'user_action' => 'PAY_NOW',
            ]]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?? 'PayPal error');
        }

        return (string) collect($response->json('links'))->firstWhere('rel', 'payer-action')['href'];
    }

    /**
     * Capture an approved order (from the return URL) and record the payment.
     */
    public static function capture(Invoice $invoice, string $orderId): bool
    {
        $response = self::api()->withBody('{}', 'application/json')->post("/v2/checkout/orders/{$orderId}/capture");
        $order = $response->successful() ? $response->json() : self::api()->get("/v2/checkout/orders/{$orderId}")->json();

        $unit = $order['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? null;

        if (($order['status'] ?? null) !== 'COMPLETED' || ! $capture || ($capture['custom_id'] ?? $unit['custom_id'] ?? null) !== $invoice->token) {
            return false;
        }

        $fee = (float) ($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0);
        Payments::record($invoice, (float) $capture['amount']['value'], 'paypal', (string) $capture['id'], $fee);

        return true;
    }

    /**
     * Import PayPal transactions from the last sync (up to 31 days at a time).
     * Needs "Transaction search" enabled on the PayPal app.
     */
    public static function sync(): int
    {
        $from = now()->setTimestamp((int) TokenStore::get('paypal_sync_after', now()->subDays(30)->timestamp));
        $to = $from->copy()->addDays(31)->min(now());
        $added = 0;
        $page = 1;

        do {
            $result = self::api()->get('/v1/reporting/transactions', [
                'start_date' => $from->toIso8601String(),
                'end_date' => $to->toIso8601String(),
                'fields' => 'transaction_info,payer_info',
                'page_size' => 500,
                'page' => $page,
            ])->throw()->json();

            foreach ($result['transaction_details'] ?? [] as $detail) {
                $added += self::importTransaction($detail);
            }
        } while ($page++ < ($result['total_pages'] ?? 1));

        TokenStore::put('paypal_sync_after', $to->timestamp);

        return $added;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private static function importTransaction(array $detail): int
    {
        $info = $detail['transaction_info'] ?? [];
        $id = (string) ($info['transaction_id'] ?? '');
        $value = (float) ($info['transaction_amount']['value'] ?? 0);

        if ($id === '' || $value == 0 || ($info['transaction_status'] ?? 'S') !== 'S' || Transaction::where('source', 'paypal')->where('external_id', $id)->exists()) {
            return 0;
        }

        $email = $detail['payer_info']['email_address'] ?? null;
        $name = $detail['payer_info']['payer_name']['alternate_full_name'] ?? null;
        $contact = $email ? (Contact::findByEmail($email) ?? (Settings::get('sync_create_contacts', true) ? LeadCapture::upsert(['email' => $email] + Stripe::names($name), 'customer') : null)) : null;

        Transaction::create([
            'title' => $info['transaction_subject'] ?? $info['transaction_note'] ?? __('PayPal payment'),
            'reference' => $id,
            'type' => $value < 0 ? 'refund' : 'sale',
            'status' => 'succeeded',
            'amount' => abs($value),
            'fee' => abs((float) ($info['fee_amount']['value'] ?? 0)),
            'currency' => strtoupper((string) ($info['transaction_amount']['currency_code'] ?? Settings::currency())),
            'date' => substr((string) ($info['transaction_initiation_date'] ?? now()->toDateString()), 0, 10),
            'contact_id' => $contact?->id,
            'source' => 'paypal',
            'external_id' => $id,
        ]);

        return 1;
    }
}
