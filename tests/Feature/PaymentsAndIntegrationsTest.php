<?php

namespace RadThemes\RadpackCrm\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\RadpackCrm\Integrations\GoogleContacts;
use RadThemes\RadpackCrm\Mail\DocumentMail;
use RadThemes\RadpackCrm\Models\Automation;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Note;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Payments\Stripe;
use RadThemes\RadpackCrm\Support\TokenStore;
use RadThemes\RadpackCrm\Tests\TestCase;
use Statamic\Facades\Addon;

class PaymentsAndIntegrationsTest extends TestCase
{
    private function settings(array $values): void
    {
        Addon::get('rad-themes/radpack-crm')->settings()->set($values)->save();
    }

    private function sentInvoice(float $amount = 250): Invoice
    {
        $invoice = Invoice::factory()->create(['contact_id' => Contact::factory()->create(['email' => 'maya@example.com'])->id, 'currency' => 'USD']);
        $invoice->syncItems([['description' => 'Design', 'quantity' => 1, 'unit_price' => $amount]], 0);
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);

        return $invoice->fresh();
    }

    #[Test]
    public function invoices_are_paid_with_stripe_checkout(): void
    {
        $this->settings(['stripe_secret_key' => 'sk_test_123']);
        $invoice = $this->sentInvoice(250);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']),
            'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response([
                'id' => 'cs_1', 'payment_status' => 'paid', 'amount_total' => 25000, 'currency' => 'usd',
                'payment_intent' => 'pi_1', 'metadata' => ['radpack_invoice' => $invoice->token],
            ]),
        ]);

        $this->get(route('statamic.radpack-crm.public.invoice', $invoice->token))->assertSee('Pay by card');

        $this->post(route('statamic.radpack-crm.public.invoice.pay', [$invoice->token, 'stripe']))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'checkout/sessions')
            && $request['line_items'][0]['price_data']['unit_amount'] === 25000
            && $request['metadata']['radpack_invoice'] === $invoice->token);

        $this->get(route('statamic.radpack-crm.public.invoice.paid', [$invoice->token, 'stripe']).'?session_id=cs_1')
            ->assertRedirect()->assertSessionHas('radpack_crm_status', 'Thank you, your payment has been received.');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('pi_1', Transaction::sole()->external_id);

        // The webhook for the same payment doesn't record it twice.
        Stripe::recordSession(['payment_status' => 'paid', 'amount_total' => 25000, 'currency' => 'usd', 'payment_intent' => 'pi_1', 'metadata' => ['radpack_invoice' => $invoice->token]]);
        $this->assertSame(1, Transaction::count());
    }

    #[Test]
    public function stripe_webhooks_must_be_signed(): void
    {
        $this->settings(['stripe_secret_key' => 'sk_test_123', 'stripe_webhook_secret' => 'whsec_abc']);
        $invoice = $this->sentInvoice(100);
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_2', 'payment_status' => 'paid', 'amount_total' => 10000, 'currency' => 'usd', 'payment_intent' => 'pi_2', 'metadata' => ['radpack_invoice' => $invoice->token],
        ]]]);
        $time = time();
        $signature = hash_hmac('sha256', "{$time}.{$payload}", 'whsec_abc');

        $this->call('POST', route('statamic.radpack-crm.webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$time},v1=bad", 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->call('POST', route('statamic.radpack-crm.webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.($time - 3600).",v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);
        $this->assertSame('sent', $invoice->fresh()->status);

        $this->call('POST', route('statamic.radpack-crm.webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    #[Test]
    public function stripe_charges_and_refunds_are_imported(): void
    {
        $this->settings(['stripe_secret_key' => 'sk_test_123']);
        Http::fake(['api.stripe.com/v1/charges*' => Http::response(['has_more' => false, 'data' => [
            ['id' => 'ch_1', 'payment_intent' => 'pi_9', 'status' => 'succeeded', 'paid' => true, 'amount' => 5000, 'amount_refunded' => 1000, 'currency' => 'gbp', 'created' => now()->timestamp, 'description' => 'T-shirts',
                'billing_details' => ['email' => 'new@example.com', 'name' => 'Nina Patel'], 'balance_transaction' => ['fee' => 175]],
            ['id' => 'ch_2', 'status' => 'failed', 'paid' => false, 'amount' => 900, 'currency' => 'gbp', 'created' => now()->timestamp],
        ]])]);

        $this->artisan('radpack-crm:sync', ['service' => 'stripe'])->assertSuccessful();

        $contact = Contact::findByEmail('new@example.com');
        $this->assertSame(['Nina', 'Patel', 'customer'], [$contact->first_name, $contact->last_name, $contact->status]);
        $sale = Transaction::where('type', 'sale')->sole();
        $this->assertSame([50.0, 1.75, 'GBP', $contact->id], [(float) $sale->amount, (float) $sale->fee, $sale->currency, $sale->contact_id]);
        $this->assertSame(10.0, (float) Transaction::where('type', 'refund')->sole()->amount);

        // Running again adds nothing.
        $this->artisan('radpack-crm:sync', ['service' => 'stripe']);
        $this->assertSame(2, Transaction::count());
        $this->assertSame(0, Stripe::toMinor(0, 'USD'));
        $this->assertSame(1000, Stripe::toMinor(1000, 'JPY'));
    }

    #[Test]
    public function invoices_are_paid_with_paypal(): void
    {
        $this->settings(['paypal_client_id' => 'id', 'paypal_secret' => 'secret', 'paypal_mode' => 'sandbox']);
        $invoice = $this->sentInvoice(80);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER1', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER1']]]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER1/capture' => Http::response(['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAP1', 'custom_id' => $invoice->token, 'amount' => ['value' => '80.00', 'currency_code' => 'USD'], 'seller_receivable_breakdown' => ['paypal_fee' => ['value' => '3.10']],
            ]]]]]]),
        ]);

        $this->post(route('statamic.radpack-crm.public.invoice.pay', [$invoice->token, 'paypal']))->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER1');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'v2/checkout/orders') && $request['purchase_units'][0]['amount']['value'] === '80.00');

        $this->get(route('statamic.radpack-crm.public.invoice.paid', [$invoice->token, 'paypal']).'?token=ORDER1')->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(['CAP1', 3.1], [Transaction::sole()->external_id, (float) Transaction::sole()->fee]);
    }

    #[Test]
    public function contacts_sync_to_mailchimp_and_kit(): void
    {
        $this->settings(['mailchimp_api_key' => 'abc-us21', 'mailchimp_list_id' => 'L1', 'kit_api_key' => 'kit_123', 'list_sync_tags' => ['Newsletter']]);
        Http::fake([
            'us21.api.mailchimp.com/*' => Http::response([]),
            'api.kit.com/v4/tags' => Http::response(['tag' => ['id' => 77]]),
            'api.kit.com/*' => Http::response([]),
        ]);

        Contact::factory()->create(['email' => 'nobody@example.com']);
        app()->terminate();
        Http::assertNothingSent();

        $contact = Contact::factory()->create(['email' => 'Maya@Example.com', 'first_name' => 'Maya']);
        $contact->attachTags(['Newsletter']);
        app()->terminate();

        $hash = md5('maya@example.com');
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === "https://us21.api.mailchimp.com/3.0/lists/L1/members/{$hash}"
            && $request['status_if_new'] === 'subscribed' && $request['merge_fields']['FNAME'] === 'Maya');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), "members/{$hash}/tags") && $request['tags'][0]['name'] === 'Newsletter');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.kit.com/v4/subscribers' && $request->hasHeader('X-Kit-Api-Key', 'kit_123'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.kit.com/v4/tags/77/subscribers');
    }

    #[Test]
    public function text_messages_go_through_twilio(): void
    {
        $this->settings(['twilio_sid' => 'AC1', 'twilio_token' => 'tok', 'twilio_from' => '+15550001', 'twilio_country_code' => '44']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $contact = Contact::factory()->create(['phone' => '07700 900123', 'first_name' => 'Leo']);

        $this->actingAs($this->admin())->post(cp_route('radpack-crm.contacts.sms.store', $contact), ['body' => 'Running late, 10 mins'])
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC1/Messages.json'
            && $request['To'] === '+447700900123' && $request['From'] === '+15550001');
        $this->assertSame('sms', Note::sole()->type);

        Automation::create(['name' => 'Text', 'match' => 'all', 'active' => true, 'trigger' => 'contact.tagged', 'actions' => [['type' => 'send_sms', 'text' => 'Hi {{ first_name }}!']]]);
        $contact->attachTags(['VIP']);
        Http::assertSent(fn (Request $request) => $request['Body'] === 'Hi Leo!');
    }

    #[Test]
    public function google_contacts_are_imported(): void
    {
        $this->settings(['google_client_id' => 'gid', 'google_client_secret' => 'gsecret']);
        TokenStore::put('google_refresh_token', 'refresh');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29']),
            'people.googleapis.com/*' => Http::response(['connections' => [
                ['names' => [['givenName' => 'Omar', 'familyName' => 'Ali']], 'emailAddresses' => [['value' => 'omar@acme.test']], 'organizations' => [['name' => 'Acme']]],
                ['names' => [['givenName' => 'No email']]],
            ]]),
        ]);

        $this->assertSame(1, GoogleContacts::import());
        $contact = Contact::findByEmail('omar@acme.test');
        $this->assertSame(['Omar', 'Acme', ['Google Contacts']], [$contact->first_name, $contact->company->name, $contact->tags->pluck('name')->all()]);

        TokenStore::forget('google_refresh_token');
    }

    #[Test]
    public function invoice_emails_use_the_custom_wording(): void
    {
        $this->settings(['invoice_email_subject' => '{{ business_name }} invoice {{ number }}', 'invoice_email_message' => 'Hello {{ name }}, {{ total }} is due {{ due_date }}.', 'business_name' => 'Rad Studio']);
        $invoice = $this->sentInvoice(99);
        $invoice->contact->update(['first_name' => 'Maya']);

        $mail = new DocumentMail($invoice->fresh());

        $this->assertSame("Rad Studio invoice {$invoice->number}", $mail->envelope()->subject);
        $this->assertStringContainsString('Hello Maya, $99.00 is due', $mail->render());
        $this->assertStringContainsString('Personal note', (new DocumentMail($invoice->fresh(), 'Personal note'))->render());
    }

    #[Test]
    public function the_integrations_page_and_white_label_name_work(): void
    {
        $this->settings(['crm_name' => 'Pipeline']);
        $admin = $this->makeUser('super@example.com');
        $admin->makeSuper()->save();

        $this->actingAs($admin)->get(cp_route('radpack-crm.integrations'))->assertOk();
        $this->actingAs($admin)->get(cp_route('radpack-crm.dashboard'))->assertInertia(fn ($page) => $page->where('crmName', 'Pipeline'));
        $this->actingAs($admin)->get(cp_route('radpack-crm.integrations.callback', 'google').'?code=x&state=forged')
            ->assertRedirect(cp_route('radpack-crm.integrations'))->assertSessionHas('error');
    }
}
