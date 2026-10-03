<?php

namespace RadThemes\AlpCrm\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\AlpCrm\Mail\DocumentMail;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Models\Transaction;
use RadThemes\AlpCrm\Support\Documents;
use RadThemes\AlpCrm\Tests\TestCase;
use Statamic\Facades\Addon;

class SalesTest extends TestCase
{
    #[Test]
    public function totals_handle_tax_on_top_tax_included_and_discounts(): void
    {
        $lines = [
            ['quantity' => 2, 'unit_price' => 100, 'tax_rate' => 20],
            ['quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
        ];

        $this->assertSame(['subtotal' => 250.0, 'discount' => 0.0, 'tax_total' => 40.0, 'total' => 290.0, 'lines' => [200.0, 50.0]], Invoice::calculate($lines));

        // A discount reduces the taxable amount proportionally: 50 off 250 → 200 + tax 40 × 0.8 = 232.
        $this->assertSame(232.0, Invoice::calculate($lines, 50)['total']);
        $this->assertSame(250.0, Invoice::calculate($lines, 9999)['discount'], 'A discount never exceeds the subtotal');

        // Tax-inclusive prices: 120 incl. 20% tax = 100 net + 20 tax.
        $inclusive = Invoice::calculate([['quantity' => 1, 'unit_price' => 120, 'tax_rate' => 20]], 0, true);
        $this->assertSame([100.0, 20.0, 120.0], [$inclusive['subtotal'], $inclusive['tax_total'], $inclusive['total']]);
    }

    #[Test]
    public function an_invoice_is_created_from_the_editor_with_sequential_numbers(): void
    {
        $contact = Contact::factory()->create();
        $admin = $this->admin();

        $payload = [
            'contact_id' => $contact->id,
            'title' => 'Website build',
            'currency' => 'eur',
            'issue_date' => '2026-10-01',
            'second_date' => '2026-10-31',
            'discount' => 10,
            'items' => [
                ['description' => 'Design', 'quantity' => 2, 'unit_price' => 100, 'tax_name' => 'VAT', 'tax_rate' => 20],
                ['description' => '', 'quantity' => 1, 'unit_price' => 999],
            ],
        ];

        $this->actingAs($admin)->post(cp_route('alp-crm.invoices.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(cp_route('alp-crm.invoices.store'), $payload)->assertRedirect();

        [$first, $second] = Invoice::orderBy('id')->get();
        $this->assertSame(['INV-0001', 'INV-0002'], [$first->number, $second->number]);
        $this->assertSame('EUR', $first->currency);
        $this->assertSame('2026-10-31', $first->due_date->format('Y-m-d'));
        $this->assertCount(1, $first->items, 'Blank lines are dropped');
        $this->assertSame([200.0, 10.0, 38.0, 228.0], [$first->subtotal, $first->discount, $first->tax_total, $first->total]);
        $this->assertSame('draft', $first->status);
    }

    #[Test]
    public function the_editor_validates_its_input(): void
    {
        $this->actingAs($this->admin())
            ->post(cp_route('alp-crm.invoices.store'), ['currency' => 'EURO', 'issue_date' => 'not a date', 'second_date' => '2020-01-01', 'items' => [['description' => 'X', 'quantity' => 'lots']]])
            ->assertSessionHasErrors(['currency', 'issue_date', 'items.0.quantity']);

        $this->assertSame(0, Invoice::count());
    }

    #[Test]
    public function payments_move_an_invoice_through_partly_paid_to_paid_and_refunds_reopen_it(): void
    {
        $invoice = Invoice::factory()->sent()->withItems([['description' => 'Work', 'quantity' => 1, 'unit_price' => 300]])->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(cp_route('alp-crm.invoices.payments.store', $invoice->id), ['amount' => 100, 'date' => '2026-10-02'])->assertRedirect();
        $invoice->refresh();
        $this->assertSame(['partial', 100.0, 200.0], [$invoice->status, $invoice->amount_paid, $invoice->balance()]);
        $this->assertSame(['Status changed: Sent → Partly paid'], $invoice->activities()->where('event', 'status_changed')->pluck('description')->all(), 'One payment logs one status change');

        $this->actingAs($admin)->post(cp_route('alp-crm.invoices.payments.store', $invoice->id), ['amount' => 200, 'date' => '2026-10-03']);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('invoice_paid', $invoice->contact->activities()->first()->event);

        $invoice->recordPayment(50, ['type' => 'refund']);
        $this->assertSame(['partial', 250.0], [$invoice->fresh()->status, $invoice->fresh()->amount_paid]);

        $invoice->payments()->where('type', 'refund')->first()->delete();
        $this->assertSame('paid', $invoice->fresh()->status, 'Deleting a payment recalculates the invoice');
    }

    #[Test]
    public function invoices_become_overdue_and_can_be_voided(): void
    {
        $invoice = Invoice::factory()->sent()->withItems()->create(['due_date' => today()->subDay()]);

        $this->assertSame('overdue', $invoice->displayStatus());
        $this->assertSame(1, Invoice::overdue()->count());

        $this->actingAs($this->admin())->post(cp_route('alp-crm.invoices.void', $invoice->id));
        $this->assertSame('void', $invoice->fresh()->status);

        $this->actingAs($this->admin())
            ->post(cp_route('alp-crm.invoices.payments.store', $invoice->id), ['amount' => 10, 'date' => '2026-10-02'])
            ->assertStatus(422);
    }

    #[Test]
    public function sending_emails_the_client_with_the_pdf_and_marks_it_sent(): void
    {
        Mail::fake();
        $invoice = Invoice::factory()->withItems()->create();

        $this->actingAs($this->admin())
            ->post(cp_route('alp-crm.invoices.send', $invoice->id), ['to' => 'client@example.com', 'message' => 'Thanks!'])
            ->assertRedirect();

        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('client@example.com')
            && $mail->personalMessage === 'Thanks!'
            && count($mail->attachments()) === 1);

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->sent_at);
    }

    #[Test]
    public function the_email_and_pdf_render(): void
    {
        $invoice = Invoice::factory()->withItems()->create(['title' => 'Website build']);

        $this->assertStringStartsWith('%PDF', Documents::pdf($invoice));
        $this->assertStringContainsString('Website build', (new DocumentMail($invoice))->render());

        $this->actingAs($this->admin())
            ->get(cp_route('alp-crm.invoices.pdf', $invoice->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function clients_see_sent_documents_by_token_but_never_drafts(): void
    {
        $invoice = Invoice::factory()->withItems()->create();

        $this->get(Documents::publicUrl($invoice))->assertNotFound();
        $this->get(route('statamic.alp-crm.public.invoice', 'not-a-real-token'))->assertNotFound();

        $invoice->update(['status' => 'sent']);

        $this->get(Documents::publicUrl($invoice))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Download PDF')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get(route('statamic.alp-crm.public.invoice.pdf', $invoice->token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function clients_accept_quotes_once_and_quotes_convert_to_invoices(): void
    {
        $quote = Quote::factory()->sent()->withItems([['description' => 'Logo', 'quantity' => 1, 'unit_price' => 800, 'tax_name' => 'VAT', 'tax_rate' => 10]], 100)->create();

        $this->get(Documents::publicUrl($quote))->assertOk()->assertSee('Accept quote');

        $this->from(Documents::publicUrl($quote))
            ->post(route('statamic.alp-crm.public.quote.respond', $quote->token), ['accepted' => '1'])
            ->assertRedirect(Documents::publicUrl($quote));

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame($quote->contact->name(), $quote->responded_by);

        $this->post(route('statamic.alp-crm.public.quote.respond', $quote->token), ['accepted' => '0']);
        $this->assertSame('accepted', $quote->fresh()->status, 'A quote cannot be answered twice');

        $this->actingAs($this->admin())->post(cp_route('alp-crm.quotes.convert', $quote->id))->assertRedirect();
        $invoice = Invoice::firstOrFail();
        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame([700.0, 70.0, 770.0], [$invoice->subtotal - $invoice->discount, $invoice->tax_total, $invoice->total]);

        $this->actingAs($this->admin())->post(cp_route('alp-crm.quotes.convert', $quote->id));
        $this->assertSame(1, Invoice::count(), 'Converting twice reuses the same invoice');
    }

    #[Test]
    public function expired_quotes_cannot_be_accepted(): void
    {
        $quote = Quote::factory()->sent()->withItems()->create(['valid_until' => today()->subDay()]);

        $this->assertSame('expired', $quote->displayStatus());
        $this->post(route('statamic.alp-crm.public.quote.respond', $quote->token), ['accepted' => '1']);
        $this->assertSame('sent', $quote->fresh()->status);
    }

    #[Test]
    public function transactions_are_created_from_the_blueprint_and_count_towards_lifetime_value(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->actingAs($this->admin())->postJson(cp_route('alp-crm.transactions.store'), [
            'title' => 'Annual plan',
            'contact' => [$contact->id],
            'amount' => 1200,
            'currency' => 'USD',
            'date' => '2026-10-01',
            'type' => 'sale',
            'status' => 'succeeded',
        ]);
        $response->assertOk();
        $this->assertSame('2026-10-01', Transaction::firstOrFail()->date->format('Y-m-d'));

        Transaction::factory()->create(['contact_id' => $contact->id, 'amount' => 200, 'type' => 'refund']);
        Transaction::factory()->create(['contact_id' => $contact->id, 'amount' => 999, 'status' => 'failed']);
        Transaction::factory()->create(['contact_id' => $contact->id, 'amount' => 999, 'currency' => 'EUR']);

        $this->assertSame(1000.0, $contact->lifetimeValue());

        $this->actingAs($this->admin())->get(cp_route('alp-crm.contacts.show', $contact))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('sales.lifetime_value', '$1,000.00')->has('sales.transactions', 4));
    }

    #[Test]
    public function settings_drive_numbering_currency_and_terms(): void
    {
        Addon::get('rad-themes/alp-crm')->settings()->set([
            'invoice_prefix' => '2026/',
            'currency' => ['GBP'],
            'payment_terms_days' => 14,
            'invoice_terms' => 'Pay within 14 days.',
        ])->save();

        $invoice = Invoice::create(['contact_id' => Contact::factory()->create()->id]);

        $this->assertSame('2026/0001', $invoice->number);
        $this->assertSame('GBP', $invoice->currency);
        $this->assertSame(today()->addDays(14)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame('Pay within 14 days.', $invoice->terms);

        File::delete(resource_path('addons/alp-crm.yaml'));
    }

    #[Test]
    public function dashboard_activity_links_to_the_right_record(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->admin())->get(cp_route('alp-crm.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activity.0.subject', $invoice->number)
                ->where('activity.0.url', cp_route('alp-crm.invoices.show', $invoice))
                ->where('activity.1.url', cp_route('alp-crm.contacts.show', $invoice->contact)));
    }

    #[Test]
    public function viewers_cannot_change_sales_records(): void
    {
        $invoice = Invoice::factory()->sent()->withItems()->create();
        $viewer = $this->makeUser('viewer@example.com', 'crm_viewer');

        $this->actingAs($viewer)->get(cp_route('alp-crm.invoices.show', $invoice->id))->assertOk();
        $this->actingAs($viewer)->postJson(cp_route('alp-crm.invoices.payments.store', $invoice->id), ['amount' => 1, 'date' => '2026-10-02'])->assertForbidden();
        $this->actingAs($viewer)->postJson(cp_route('alp-crm.invoices.send', $invoice->id), ['to' => 'a@b.test'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(cp_route('alp-crm.invoices.destroy', $invoice->id))->assertForbidden();
    }
}
