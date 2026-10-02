<?php

namespace RadThemes\RadpackCrm\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\RadpackCrm\Email\ContactMail;
use RadThemes\RadpackCrm\Models\Automation;
use RadThemes\RadpackCrm\Models\AutomationRun;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\EmailTemplate;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Tests\TestCase;

class AutomationsTest extends TestCase
{
    private function automation(array $attributes): Automation
    {
        return Automation::create($attributes + ['name' => 'Test', 'match' => 'all', 'active' => true]);
    }

    #[Test]
    public function an_automation_runs_its_actions_when_triggered(): void
    {
        $this->automation([
            'trigger' => 'contact.created',
            'actions' => [
                ['type' => 'add_tag', 'tags' => 'New, Welcome'],
                ['type' => 'create_task', 'title' => 'Call {{ first_name }}', 'task_type' => 'call', 'due_in_days' => 2],
                ['type' => 'add_note', 'body' => 'Welcomed {{ name }}'],
            ],
        ]);

        $contact = Contact::factory()->create(['first_name' => 'Maya', 'last_name' => 'Chen']);

        $this->assertEqualsCanonicalizing(['New', 'Welcome'], $contact->tags()->pluck('name')->all());
        $task = Task::sole();
        $this->assertSame(['Call Maya', 'call', $contact->id], [$task->title, $task->type, $task->contact_id]);
        $this->assertSame(today()->addDays(2)->toDateString(), $task->starts_at->toDateString());
        $this->assertSame('Welcomed Maya Chen', $contact->notes()->sole()->body);
        $this->assertSame(3, AutomationRun::where('status', 'done')->count());
        $this->assertSame(1, Automation::sole()->runs_count);
        $this->assertStringStartsWith('Automation “Test”', $contact->activities()->where('event', 'automation')->first()->description);
    }

    #[Test]
    public function trigger_options_and_conditions_filter_who_it_runs_for(): void
    {
        $this->automation([
            'trigger' => 'contact.tagged',
            'trigger_options' => ['tag' => 'VIP'],
            'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'customer']],
            'actions' => [['type' => 'set_status', 'status' => 'prospect']],
        ]);

        $lead = Contact::factory()->create(['status' => 'lead']);
        $customer = Contact::factory()->create(['status' => 'customer']);

        $customer->attachTags(['Newsletter']);
        $lead->attachTags(['VIP']);
        $this->assertSame(0, AutomationRun::count(), 'Wrong tag, or contact doesn’t match the conditions');

        $customer->attachTags(['vip']);
        $this->assertSame('prospect', $customer->fresh()->status);
    }

    #[Test]
    public function delayed_steps_wait_for_the_scheduler(): void
    {
        Mail::fake();
        $template = EmailTemplate::create(['name' => 'Welcome', 'subject' => 'Welcome {{ first_name }}', 'body' => 'Hi']);
        $this->automation([
            'trigger' => 'contact.status_changed',
            'trigger_options' => ['status' => 'customer'],
            'actions' => [
                ['type' => 'add_tag', 'tags' => 'Customer'],
                ['type' => 'send_email', 'template_id' => $template->id, 'delay' => 2, 'delay_unit' => 'days'],
            ],
        ]);

        $contact = Contact::factory()->create(['status' => 'lead', 'first_name' => 'Leo', 'email' => 'leo@example.com']);
        $contact->update(['status' => 'customer']);

        $this->assertSame(['Customer'], $contact->tags()->pluck('name')->all());
        Mail::assertNothingSent();

        $this->travel(1)->days();
        $this->artisan('radpack-crm:automations');
        Mail::assertNothingSent();

        $this->travel(1)->days();
        $this->artisan('radpack-crm:automations');
        Mail::assertSent(ContactMail::class, fn ($mail) => $mail->mailSubject === 'Welcome Leo');

        $this->artisan('radpack-crm:automations');
        Mail::assertSent(ContactMail::class, 1);
    }

    #[Test]
    public function steps_are_skipped_when_they_dont_apply_or_the_automation_is_paused(): void
    {
        Mail::fake();
        $template = EmailTemplate::create(['name' => 'Hi', 'subject' => 'Hi', 'body' => 'Hi']);
        $automation = $this->automation([
            'trigger' => 'contact.created',
            'actions' => [
                ['type' => 'send_email', 'template_id' => $template->id],
                ['type' => 'add_tag', 'tags' => 'Later', 'delay' => 1, 'delay_unit' => 'hours'],
            ],
        ]);

        Contact::factory()->create(['email' => null]);
        $this->assertSame('skipped', AutomationRun::where('step', 0)->sole()->status);

        $automation->update(['active' => false]);
        $this->travel(2)->hours();
        $this->artisan('radpack-crm:automations');
        $this->assertSame('skipped', AutomationRun::where('step', 1)->sole()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function automations_cannot_loop_forever(): void
    {
        // Each run tags the contact, which triggers the automation again.
        $this->automation(['trigger' => 'contact.tagged', 'actions' => [['type' => 'add_tag', 'tags' => 'Loop {{ name }}']]]);
        $this->automation(['trigger' => 'contact.tagged', 'actions' => [['type' => 'add_tag', 'tags' => (string) random_int(1, 1000)]]]);

        Contact::factory()->create()->attachTags(['Start']);

        $this->assertLessThan(20, AutomationRun::count());
    }

    #[Test]
    public function sales_events_trigger_automations(): void
    {
        Http::fake();
        $this->automation(['trigger' => 'quote.accepted', 'actions' => [['type' => 'set_status', 'status' => 'customer']]]);
        $this->automation(['trigger' => 'invoice.paid', 'actions' => [['type' => 'webhook', 'url' => 'https://hooks.example.com/paid']]]);

        $contact = Contact::factory()->create(['status' => 'lead']);
        $quote = Quote::factory()->create(['contact_id' => $contact->id, 'status' => 'sent']);
        $quote->syncItems([['description' => 'Design', 'quantity' => 1, 'unit_price' => 500]], 0);
        $quote->respond(true, 'Maya');
        $this->assertSame('customer', $contact->fresh()->status);

        $invoice = $quote->convertToInvoice();
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);
        $invoice->recordPayment((float) $invoice->total);

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.com/paid' && $request['event'] === 'invoice.paid' && $request['contact']['id'] === $contact->id);
    }

    #[Test]
    public function automations_are_created_from_the_editor(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(cp_route('radpack-crm.automations.create', ['recipe' => 'quote_accepted']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('radpack-crm::Automations/Edit')->where('values.trigger', 'quote.accepted'));

        $this->actingAs($admin)->post(cp_route('radpack-crm.automations.store'), [
            'name' => 'Welcome', 'active' => true, 'trigger' => 'form.submitted', 'trigger_options' => ['form' => 'contact', 'tag' => ''], 'match' => 'all', 'conditions' => [],
            'actions' => [['type' => 'send_email', 'template_id' => null, 'delay' => 1, 'delay_unit' => 'days']],
        ])->assertSessionHasErrors('actions.0.template_id');

        $this->actingAs($admin)->post(cp_route('radpack-crm.automations.store'), [
            'name' => 'Welcome', 'active' => true, 'trigger' => 'form.submitted', 'trigger_options' => ['form' => 'contact', 'tag' => ''], 'match' => 'all', 'conditions' => [],
            'actions' => [['type' => 'create_task', 'title' => 'Call', 'due_in_days' => 1, 'delay' => 0, 'delay_unit' => 'minutes', 'evil' => 'x']],
        ])->assertRedirect(cp_route('radpack-crm.automations.index'));

        $automation = Automation::sole();
        $this->assertSame(['form' => 'contact'], $automation->trigger_options);
        $this->assertArrayNotHasKey('evil', $automation->actions[0]);

        $this->actingAs($admin)->get(cp_route('radpack-crm.automations.index'))->assertOk();
        $this->actingAs($admin)->get(cp_route('radpack-crm.automations.edit', $automation))->assertOk();
    }

    #[Test]
    public function reports_show_the_funnel_and_revenue(): void
    {
        Contact::factory()->count(3)->create(['status' => 'lead']);
        $customer = Contact::factory()->create(['status' => 'customer']);
        Transaction::factory()->create(['contact_id' => $customer->id, 'amount' => 300, 'date' => today(), 'currency' => 'USD']);

        $this->actingAs($this->admin())->get(cp_route('radpack-crm.reports', ['period' => '30d']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('radpack-crm::Reports')
                ->where('funnel.0.status', 'lead')
                ->where('funnel.0.reached', 4)
                ->where('kpis.0.value', '$300.00')
                ->where('topCustomers.0.name', $customer->name())
                ->where('months.11.revenue', 300));
    }
}
