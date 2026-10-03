<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\AlpCrm\Integrations\GoogleContacts;
use RadThemes\AlpCrm\Integrations\Lists\AWeber;
use RadThemes\AlpCrm\Integrations\Lists\Kit;
use RadThemes\AlpCrm\Integrations\Lists\Mailchimp;
use RadThemes\AlpCrm\Integrations\Sync;
use RadThemes\AlpCrm\Integrations\Twilio;
use RadThemes\AlpCrm\Payments\Payments;
use RadThemes\AlpCrm\Payments\PayPal;
use RadThemes\AlpCrm\Payments\Stripe;
use RadThemes\AlpCrm\Support\Settings;
use RadThemes\AlpCrm\Support\TokenStore;
use Statamic\Facades\Addon;
use Statamic\Http\Controllers\CP\CpController;
use Throwable;

/**
 * Status of every integration, OAuth connections and "sync now".
 */
class IntegrationsController extends CpController
{
    public function index(): Response
    {
        $this->authorize('configure addons');

        $card = fn (string $key, string $name, string $description, bool $configured, array $extra = []) => [
            'key' => $key, 'name' => $name, 'description' => $description, 'configured' => $configured,
        ] + $extra;

        return Inertia::render('alp-crm::Integrations', [
            'settingsUrl' => Addon::get('rad-themes/alp-crm')->settingsUrl(),
            'groups' => [
                ['heading' => __('Payments'), 'items' => [
                    $card('stripe', 'Stripe', __('Card payments on invoices, and importing Stripe charges and refunds as transactions.'), Stripe::configured(), [
                        'sync' => Stripe::configured(), 'syncing' => Payments::enabledForSync('stripe'),
                        'note' => __('Webhook URL: :url (events: checkout.session.completed, charge.succeeded, charge.refunded)', ['url' => route('statamic.alp-crm.webhooks.stripe')]),
                    ]),
                    $card('paypal', 'PayPal', __('PayPal payments on invoices, and importing PayPal transactions.'), PayPal::configured(), [
                        'sync' => PayPal::configured(), 'syncing' => Payments::enabledForSync('paypal'),
                    ]),
                ]],
                ['heading' => __('Email marketing'), 'items' => [
                    $card('mailchimp', 'Mailchimp', __('Keep an audience in sync with your contacts and tags.'), Mailchimp::configured()),
                    $card('kit', 'Kit', __('Keep Kit (ConvertKit) subscribers and tags in sync.'), Kit::configured()),
                    $card('aweber', 'AWeber', __('Keep an AWeber list in sync with your contacts and tags.'), AWeber::configured(), [
                        'connectable' => Settings::secret('aweber_client_id') && Settings::secret('aweber_client_secret'),
                        'connected' => (bool) TokenStore::get('aweber_refresh_token'),
                        'note' => __('Redirect URL for your AWeber app: :url', ['url' => cp_route('alp-crm.integrations.callback', 'aweber')]),
                    ]),
                    ['key' => 'lists', 'name' => __('Sync every contact now'), 'description' => __('Pushes all contacts to the connected lists (normally they sync as they change).'), 'configured' => Sync::available('lists'), 'sync' => Sync::available('lists')],
                ]],
                ['heading' => __('Contacts'), 'items' => [
                    $card('google', 'Google Contacts', __('Import contacts from a Google account.'), GoogleContacts::configured(), [
                        'connectable' => GoogleContacts::configured(),
                        'connected' => GoogleContacts::connected(),
                        'sync' => Sync::available('google'),
                        'syncing' => (bool) Settings::get('google_sync', false),
                        'last' => TokenStore::get('google_last_import'),
                        'note' => __('Redirect URI for your Google OAuth client: :url', ['url' => cp_route('alp-crm.integrations.callback', 'google')]),
                    ]),
                    $card('twilio', 'Twilio', __('Send text messages to contacts and from automations.'), Twilio::configured()),
                ]],
            ],
        ]);
    }

    public function connect(Request $request, string $service): RedirectResponse
    {
        $this->authorize('configure addons');

        $state = Str::random(40);
        $request->session()->put("alp_crm_oauth_{$service}", $state);
        $redirect = cp_route('alp-crm.integrations.callback', $service);

        return redirect()->away($service === 'google' ? GoogleContacts::authorizeUrl($redirect, $state) : AWeber::authorizeUrl($redirect, $state));
    }

    public function callback(Request $request, string $service): RedirectResponse
    {
        $this->authorize('configure addons');

        $expected = $request->session()->pull("alp_crm_oauth_{$service}");
        $back = redirect()->to(cp_route('alp-crm.integrations'));

        if (! $expected || ! hash_equals($expected, (string) $request->query('state')) || ! $request->filled('code')) {
            return $back->with('error', __('The connection was cancelled or expired. Please try again.'));
        }

        try {
            $service === 'google'
                ? GoogleContacts::connect((string) $request->query('code'), cp_route('alp-crm.integrations.callback', 'google'))
                : AWeber::connect((string) $request->query('code'), cp_route('alp-crm.integrations.callback', 'aweber'));
        } catch (Throwable $e) {
            report($e);

            return $back->with('error', __('Couldn’t connect: :error', ['error' => Str::limit($e->getMessage(), 200)]));
        }

        return $back->with('success', __('Connected'));
    }

    public function disconnect(string $service): RedirectResponse
    {
        $this->authorize('configure addons');

        $service === 'google' ? GoogleContacts::disconnect() : TokenStore::forget('aweber_refresh_token');

        return back()->with('success', __('Disconnected'));
    }

    public function sync(string $service): RedirectResponse
    {
        $this->authorize('configure addons');

        abort_unless(Sync::available($service), 422);

        try {
            return back()->with('success', Sync::run($service));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', Str::limit($e->getMessage(), 300));
        }
    }
}
