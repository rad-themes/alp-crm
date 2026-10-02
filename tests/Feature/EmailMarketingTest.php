<?php

namespace RadThemes\RadpackCrm\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\RadpackCrm\Email\CampaignMail;
use RadThemes\RadpackCrm\Email\CampaignSender;
use RadThemes\RadpackCrm\Email\ContactMail;
use RadThemes\RadpackCrm\Email\Tracking;
use RadThemes\RadpackCrm\Models\Campaign;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Email;
use RadThemes\RadpackCrm\Models\Segment;
use RadThemes\RadpackCrm\Models\Transaction;
use RadThemes\RadpackCrm\Tests\TestCase;

class EmailMarketingTest extends TestCase
{
    #[Test]
    public function a_contact_can_be_emailed_with_merge_tags_and_it_is_logged(): void
    {
        Mail::fake();
        $contact = Contact::factory()->create(['first_name' => 'Maya', 'email' => 'maya@example.com', 'company_id' => Company::factory()->create(['name' => 'Northwind'])->id]);

        $this->actingAs($this->admin())->post(cp_route('radpack-crm.contacts.emails.store', $contact), [
            'subject' => 'Hello {{ first_name }}',
            'body' => "Hi {{ first_name }} at {{ company }}.\n\n{{ collection:pages }}{{ title }}{{ /collection:pages }}",
        ])->assertRedirect()->assertSessionHas('success');

        $email = Email::sole();
        $this->assertSame('sent', $email->status);
        $this->assertSame('Hello Maya', $email->subject);
        $this->assertStringStartsWith('Hi Maya at Northwind.', $email->body);
        $this->assertStringNotContainsString('collection', $email->body, 'Tags are not available in untrusted templates');

        Mail::assertSent(ContactMail::class, fn (ContactMail $mail) => $mail->hasTo('maya@example.com') && $mail->mailSubject === 'Hello Maya');
        $this->assertNotNull($contact->fresh()->last_contacted_at);
        $this->assertSame('email_sent', $contact->activities()->latest('id')->first()->event);
    }

    #[Test]
    public function scheduled_emails_are_sent_by_the_scheduler_and_can_be_cancelled(): void
    {
        Mail::fake();
        $contact = Contact::factory()->create(['email' => 'leo@example.com']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(cp_route('radpack-crm.contacts.emails.store', $contact), [
            'subject' => 'Later', 'body' => 'Body', 'send_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success', 'Email scheduled');
        $this->actingAs($admin)->post(cp_route('radpack-crm.contacts.emails.store', $contact), [
            'subject' => 'Cancelled', 'body' => 'Body', 'send_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]);
        $this->actingAs($admin)->post(cp_route('radpack-crm.emails.cancel', Email::where('subject', 'Cancelled')->sole()));

        $this->artisan('radpack-crm:send-emails');
        Mail::assertNothingSent();

        $this->travel(61)->minutes();
        $this->artisan('radpack-crm:send-emails');

        Mail::assertSent(ContactMail::class, 1);
        $this->assertSame('sent', Email::where('subject', 'Later')->sole()->status);
        $this->assertSame('cancelled', Email::where('subject', 'Cancelled')->sole()->status);
    }

    #[Test]
    public function viewers_cannot_send_emails(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->makeUser('viewer@example.com', 'crm_viewer'))
            ->postJson(cp_route('radpack-crm.contacts.emails.store', $contact), ['subject' => 'Hi', 'body' => 'Hi'])
            ->assertForbidden();
    }

    #[Test]
    public function segments_match_contacts_by_rules(): void
    {
        $vip = Contact::factory()->create(['status' => 'customer', 'email' => 'vip@acme.test', 'last_contacted_at' => now()->subDays(120), 'data' => ['city' => 'Leeds']]);
        $vip->syncTags(['VIP']);
        $recent = Contact::factory()->create(['status' => 'customer', 'email' => 'recent@acme.test', 'last_contacted_at' => now()->subDays(5)]);
        $recent->syncTags(['VIP']);
        $lead = Contact::factory()->create(['status' => 'lead', 'email' => 'lead@other.test']);
        Transaction::factory()->create(['contact_id' => $vip->id, 'amount' => 600, 'status' => 'succeeded', 'type' => 'sale']);
        Transaction::factory()->create(['contact_id' => $vip->id, 'amount' => 200, 'status' => 'succeeded', 'type' => 'refund']);

        $ids = fn (array $conditions, string $match = 'all') => Segment::applyTo(Contact::query(), $conditions, $match)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$vip->id], $ids([
            ['field' => 'status', 'operator' => 'is', 'value' => 'customer'],
            ['field' => 'tag', 'operator' => 'has', 'value' => 'vip'],
            ['field' => 'last_contacted_at', 'operator' => 'older_than_days', 'value' => 90],
        ]));
        $this->assertSame([$vip->id, $lead->id], $ids([
            ['field' => 'last_contacted_at', 'operator' => 'older_than_days', 'value' => 90],
        ]), 'Never contacted counts as not contacted recently');
        $this->assertSame([$lead->id], $ids([['field' => 'tag', 'operator' => 'has_not', 'value' => 'vip']]));
        $this->assertSame([$lead->id], $ids([['field' => 'email', 'operator' => 'ends_with', 'value' => '@other.test']]));
        $this->assertSame([$vip->id], $ids([['field' => 'lifetime_value', 'operator' => 'gte', 'value' => 400]]), 'Refunds reduce lifetime value');
        $this->assertSame([], $ids([['field' => 'lifetime_value', 'operator' => 'gte', 'value' => 401]]));
        $this->assertSame([$vip->id], $ids([['field' => 'field', 'key' => 'city', 'operator' => 'equals', 'value' => 'Leeds']]));
        $this->assertSame([], $ids([['field' => 'field', 'key' => 'city) or 1=1 --', 'operator' => 'set']]), 'Unsafe keys match nothing');
        $this->assertSame([$recent->id, $lead->id], $ids([
            ['field' => 'status', 'operator' => 'is', 'value' => 'lead'],
            ['field' => 'last_contacted_at', 'operator' => 'within_days', 'value' => 30],
        ], 'any'));
    }

    #[Test]
    public function segments_can_be_saved_previewed_and_bulk_tagged(): void
    {
        $customer = Contact::factory()->create(['status' => 'customer']);
        Contact::factory()->create(['status' => 'lead']);
        $admin = $this->admin();
        $conditions = [['field' => 'status', 'operator' => 'is', 'value' => 'customer']];

        $this->actingAs($admin)->postJson(cp_route('radpack-crm.segments.preview'), ['match' => 'all', 'conditions' => $conditions])
            ->assertOk()->assertJson(['count' => 1, 'sample' => [$customer->name()]]);

        $this->actingAs($admin)->post(cp_route('radpack-crm.segments.store'), ['name' => 'Customers', 'match' => 'all', 'conditions' => $conditions])
            ->assertRedirect(cp_route('radpack-crm.segments.index'));
        $segment = Segment::sole();

        $this->actingAs($admin)->post(cp_route('radpack-crm.segments.tag', $segment), ['tags' => ['Newsletter', 'Q4']])
            ->assertSessionHas('success', 'Tagged 1 contact');
        $this->assertEqualsCanonicalizing(['Newsletter', 'Q4'], $customer->fresh()->tags->pluck('name')->all());

        // The segment filter on the contacts list.
        $filters = base64_encode(json_encode(['crm_segment' => ['segment' => (string) $segment->id]]));
        $this->actingAs($admin)->getJson(cp_route('radpack-crm.contacts.json', ['filters' => $filters]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $customer->id);
    }

    #[Test]
    public function a_campaign_goes_to_subscribed_contacts_in_its_segment_once(): void
    {
        Mail::fake();
        $segment = Segment::create(['name' => 'Customers', 'match' => 'all', 'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'customer']]]);
        $maya = Contact::factory()->create(['status' => 'customer', 'first_name' => 'Maya', 'email' => 'maya@example.com']);
        Contact::factory()->create(['status' => 'customer', 'email' => 'MAYA@example.com']);
        Contact::factory()->create(['status' => 'customer', 'email' => null]);
        Contact::factory()->create(['status' => 'customer', 'email' => 'gone@example.com', 'unsubscribed_at' => now()]);
        Contact::factory()->create(['status' => 'lead', 'email' => 'lead@example.com']);

        $admin = $this->admin();
        $this->actingAs($admin)->post(cp_route('radpack-crm.campaigns.store'), [
            'name' => 'October news', 'subject' => 'News for {{ first_name }}', 'body' => 'Hi {{ first_name }}, [read more](https://example.com/news)', 'segment_id' => $segment->id,
        ]);
        $campaign = Campaign::sole();

        $this->actingAs($admin)->post(cp_route('radpack-crm.campaigns.send', $campaign))->assertRedirect(cp_route('radpack-crm.campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->recipients()->count(), 'Duplicates, contacts without email, unsubscribed contacts and other segments are skipped');
        Mail::assertSent(CampaignMail::class, 1);

        $recipient = $campaign->recipients()->sole();
        $this->assertSame($maya->id, $recipient->contact_id);
        $mail = (new CampaignMail($recipient))->render();
        $this->assertStringContainsString('Hi Maya', $mail);
        $this->assertStringContainsString(e(Tracking::clickUrl($recipient, 'https://example.com/news')), $mail);
        $this->assertStringContainsString(route('statamic.radpack-crm.unsubscribe', $recipient->token), $mail);
        $this->assertSame('News for Maya', (new CampaignMail($recipient))->envelope()->subject);

        // Sending twice does nothing.
        $this->actingAs($admin)->post(cp_route('radpack-crm.campaigns.send', $campaign))->assertStatus(422);
    }

    #[Test]
    public function scheduled_campaigns_send_in_batches(): void
    {
        Mail::fake();
        config(['statamic.radpack-crm' => []]);
        Contact::factory()->count(3)->sequence(fn ($s) => ['email' => "c{$s->index}@example.com"])->create();
        $campaign = Campaign::create(['name' => 'Batch', 'subject' => 'S', 'body' => 'B', 'status' => 'scheduled', 'scheduled_at' => now()->addMinutes(5)]);

        CampaignSender::tick();
        $this->assertSame('scheduled', $campaign->fresh()->status);

        $this->travel(6)->minutes();
        CampaignSender::start($campaign->fresh());
        $this->assertSame(2, CampaignSender::sendBatch($campaign->fresh(), 2));
        $this->assertSame('sending', $campaign->fresh()->status);
        $this->assertSame(1, CampaignSender::sendBatch($campaign->fresh(), 2));
        $this->assertSame('sent', $campaign->fresh()->status);
        Mail::assertSent(CampaignMail::class, 3);
    }

    #[Test]
    public function opens_clicks_and_unsubscribes_are_tracked(): void
    {
        $contact = Contact::factory()->create(['email' => 'maya@example.com']);
        $campaign = Campaign::create(['name' => 'N', 'subject' => 'S', 'body' => 'B', 'status' => 'sent']);
        $recipient = $campaign->recipients()->create(['contact_id' => $contact->id, 'email' => 'maya@example.com', 'token' => str_repeat('a', 40), 'status' => 'sent', 'sent_at' => now()]);

        $this->get(route('statamic.radpack-crm.track.open', $recipient->token))->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertNotNull($recipient->fresh()->opened_at);

        $this->get(Tracking::clickUrl($recipient, 'https://example.com/a?b=1'))->assertRedirect('https://example.com/a?b=1');
        $this->get(Tracking::clickUrl($recipient, 'https://example.com/a?b=1'));
        $this->assertSame(2, $recipient->fresh()->clicks);

        // Tampered links are not an open redirect.
        $this->get(route('statamic.radpack-crm.track.click', ['token' => $recipient->token, 'u' => base64_encode('https://evil.test'), 's' => 'nope']))->assertNotFound();

        // Link scanners (GET) don't unsubscribe; the button (POST) does.
        $this->get(route('statamic.radpack-crm.unsubscribe', $recipient->token))->assertOk()->assertSee('Unsubscribe?');
        $this->assertNull($contact->fresh()->unsubscribed_at);
        $this->post(route('statamic.radpack-crm.unsubscribe.confirm', $recipient->token))->assertOk()->assertSee('You’re unsubscribed', false);
        $this->assertNotNull($contact->fresh()->unsubscribed_at);

        $stats = $campaign->stats();
        $this->assertSame(1, $stats['unsubscribed']);
        $this->assertSame(100.0, $stats['open_rate']);
        $this->assertSame(100.0, $stats['click_rate']);
    }

    #[Test]
    public function campaign_pages_render(): void
    {
        $admin = $this->admin();
        $campaign = Campaign::create(['name' => 'N', 'subject' => 'S', 'body' => 'B', 'status' => 'draft']);

        foreach ([
            cp_route('radpack-crm.campaigns.index'), cp_route('radpack-crm.campaigns.create'), cp_route('radpack-crm.campaigns.edit', $campaign),
            cp_route('radpack-crm.segments.index'), cp_route('radpack-crm.segments.create'),
            cp_route('radpack-crm.email-templates.index'), cp_route('radpack-crm.email-templates.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $campaign->update(['status' => 'sent']);
        $this->actingAs($admin)->get(cp_route('radpack-crm.campaigns.edit', $campaign))->assertRedirect(cp_route('radpack-crm.campaigns.show', $campaign));
        $this->actingAs($admin)->get(cp_route('radpack-crm.campaigns.show', $campaign))->assertOk();
    }

    #[Test]
    public function email_templates_can_be_created(): void
    {
        $this->actingAs($this->admin())->postJson(cp_route('radpack-crm.email-templates.store'), [
            'name' => 'Follow-up', 'subject' => 'Great to meet you', 'body' => 'Hi {{ first_name }}',
        ])->assertOk()->assertJson(['redirect' => cp_route('radpack-crm.email-templates.index')]);

        $this->assertDatabaseHas('crm_email_templates', ['name' => 'Follow-up', 'body' => 'Hi {{ first_name }}']);
    }
}
