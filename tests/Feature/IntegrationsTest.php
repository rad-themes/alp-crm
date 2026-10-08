<?php

namespace RadThemes\AlpCrm\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\AlpCrm\Models\ApiKey;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Webhook;
use RadThemes\AlpCrm\Support\SafeUrl;
use RadThemes\AlpCrm\Tests\TestCase;
use Statamic\Events\UserRegistered;
use Statamic\Facades\Addon;
use Statamic\Facades\Form;
use Statamic\Facades\User;

class IntegrationsTest extends TestCase
{
    private function settings(array $values): void
    {
        $addon = Addon::get('rad-themes/alp-crm');
        $addon->settings()->set($values)->save();
    }

    private function api(string $method, string $uri, array $data = [], ?string $key = null)
    {
        $key ??= ApiKey::generate('Tests')[1];

        return $this->withHeader('Authorization', "Bearer {$key}")->json($method, "/api/alp-crm/v1/{$uri}", $data);
    }

    #[Test]
    public function form_submissions_become_leads(): void
    {
        $form = tap(Form::make('contact')->title('Contact us'))->save();
        $form->blueprint()->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => 'email', 'field' => ['type' => 'text']],
            ['handle' => 'company', 'field' => ['type' => 'text']],
            ['handle' => 'city', 'field' => ['type' => 'text']],
            ['handle' => 'message', 'field' => ['type' => 'textarea', 'display' => 'Message']],
        ]]]]]])->save();
        $this->settings(['capture_forms' => ['contact'], 'capture_tags' => ['Website']]);

        $this->post('/!/forms/contact', ['name' => 'Maya Chen', 'email' => 'Maya@Example.com', 'company' => 'Northwind', 'city' => 'Leeds', 'message' => 'I need a quote'])
            ->assertRedirect();

        $contact = Contact::sole();
        $this->assertSame(['maya@example.com', 'Maya', 'Chen', 'lead', 'Northwind', 'Leeds'], [$contact->email, $contact->first_name, $contact->last_name, $contact->status, $contact->company->name, $contact->data['city']]);
        $this->assertEqualsCanonicalizing(['Website', 'Contact us'], $contact->tags->pluck('name')->all());
        $this->assertStringContainsString('Message: I need a quote', $contact->notes()->sole()->body);

        // A second submission fills gaps but doesn't overwrite.
        $this->post('/!/forms/contact', ['name' => 'Someone Else', 'email' => 'maya@example.com', 'message' => 'Again']);
        $this->assertSame(1, Contact::count());
        $this->assertSame('Maya', $contact->fresh()->first_name);
        $this->assertSame(2, $contact->notes()->count());
    }

    #[Test]
    public function forms_that_are_not_captured_are_ignored(): void
    {
        tap(Form::make('newsletter')->title('Newsletter'))->save();

        $this->post('/!/forms/newsletter', ['email' => 'a@example.com']);

        $this->assertSame(0, Contact::count());
    }

    #[Test]
    public function registered_users_become_linked_contacts(): void
    {
        $this->settings(['capture_registrations' => true, 'registration_tags' => ['Member']]);
        $user = tap(User::make()->email('leo@example.com')->data(['name' => 'Leo Martins']))->save();

        UserRegistered::dispatch($user);

        $contact = Contact::sole();
        $this->assertSame([$user->id(), 'Leo', 'Martins'], [$contact->user_id, $contact->first_name, $contact->last_name]);
        $this->assertSame(['Member'], $contact->tags->pluck('name')->all());
    }

    #[Test]
    public function registering_with_an_existing_contacts_email_does_not_link_to_it(): void
    {
        $this->settings(['capture_registrations' => true]);
        $client = Contact::factory()->create(['email' => 'maya@example.com', 'phone' => null]);
        $impostor = tap(User::make()->email('maya@example.com')->data(['name' => 'Not Maya']))->save();

        UserRegistered::dispatch($impostor);

        $contact = Contact::sole();
        $this->assertSame($client->id, $contact->id);
        $this->assertNull($contact->user_id);
    }

    #[Test]
    public function contacts_are_imported_from_csv_with_a_mapping(): void
    {
        Contact::factory()->create(['email' => 'maya@example.com', 'first_name' => 'Maya', 'phone' => null]);
        $admin = $this->admin();
        $csv = "Email Address;First Name;Surname;Phone;Company;Tags;Notes\n"
            ."maya@example.com;Maya;Chen;+44 1;Northwind;VIP;x\n"
            ."leo@example.com;Leo;Martins;;Globex;Lead, Trade show;y\n"
            ."not-an-email;Bad;Row;;;;\n";

        $response = $this->actingAs($admin)->post(cp_route('alp-crm.import.upload'), [
            'type' => 'contacts', 'file' => UploadedFile::fake()->createWithContent('people.csv', $csv),
        ]);
        $response->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

        $this->actingAs($admin)->get($response->headers->get('Location'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('alp-crm::Import')
                ->where('mapping', ['email', 'first_name', 'last_name', 'phone', 'company', 'tags', null])
                ->where('preview.total', 3));

        $this->actingAs($admin)->post(cp_route('alp-crm.import.run', $token), [
            'type' => 'contacts', 'mapping' => ['email', 'first_name', 'last_name', 'phone', 'company', 'tags', null], 'update' => true, 'tags' => ['Imported'],
        ])->assertRedirect(cp_route('alp-crm.contacts.index'))->assertSessionHas('info');

        $this->assertSame(2, Contact::count());
        $maya = Contact::findByEmail('maya@example.com');
        $this->assertSame(['Chen', '+44 1', 'Northwind'], [$maya->last_name, $maya->phone, $maya->company->name]);
        $this->assertEqualsCanonicalizing(['VIP', 'Imported'], $maya->tags->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Lead', 'Trade show', 'Imported'], Contact::findByEmail('leo@example.com')->tags->pluck('name')->all());
        $this->assertFileDoesNotExist(storage_path("app/alp-crm/imports/{$token}.csv"));
    }

    #[Test]
    public function contacts_export_to_csv_safely(): void
    {
        $contact = Contact::factory()->create(['first_name' => '=HYPERLINK("http://evil")', 'email' => 'a@example.com', 'company_id' => Company::factory()->create(['name' => 'Acme'])->id]);
        $contact->syncTags(['VIP']);

        $csv = $this->actingAs($this->admin())->get(cp_route('alp-crm.export', ['type' => 'contacts']))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Acme', $csv);
        $this->assertStringContainsString('VIP', $csv);
    }

    #[Test]
    public function the_api_requires_a_valid_key_and_respects_read_only_keys(): void
    {
        $this->getJson('/api/alp-crm/v1/contacts')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer nope')->getJson('/api/alp-crm/v1/contacts')->assertUnauthorized();

        [, $readOnly] = ApiKey::generate('Reports', false);
        $this->api('GET', 'contacts', [], $readOnly)->assertOk();
        $this->api('POST', 'contacts', ['email' => 'a@example.com'], $readOnly)->assertForbidden();
        $this->assertNotNull(ApiKey::where('name', 'Reports')->value('last_used_at'));
    }

    #[Test]
    public function contacts_can_be_managed_through_the_api(): void
    {
        $company = Company::factory()->create();

        $created = $this->api('POST', 'contacts', [
            'first_name' => 'Maya', 'email' => 'maya@example.com', 'status' => 'lead', 'company_id' => $company->id, 'tags' => ['API'], 'fields' => ['city' => 'Leeds'],
        ])->assertCreated()->assertJsonPath('data.company.id', $company->id)->assertJsonPath('data.tags', ['API'])->assertJsonPath('data.fields.city', 'Leeds');
        $id = $created->json('data.id');

        $this->api('POST', 'contacts', ['email' => 'not-an-email', 'status' => 'lead'])->assertUnprocessable();

        $this->api('PATCH', "contacts/{$id}", ['status' => 'customer'])->assertOk()
            ->assertJsonPath('data.status', 'customer')->assertJsonPath('data.first_name', 'Maya')->assertJsonPath('data.fields.city', 'Leeds');

        $this->api('POST', 'contacts/upsert', ['email' => 'MAYA@example.com', 'last_name' => 'Chen'])->assertOk()->assertJsonPath('data.id', $id);
        $this->api('POST', "contacts/{$id}/tags", ['tags' => ['VIP']])->assertOk()->assertJsonPath('data.tags', ['API', 'VIP']);
        $this->api('POST', "contacts/{$id}/notes", ['body' => 'Called', 'type' => 'call'])->assertCreated();
        $this->api('GET', 'contacts?tag=vip')->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', 'contacts?email=maya@example.com')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->api('GET', "contacts/{$id}")->assertOk()->assertJsonPath('data.last_name', 'Chen');
        $this->api('DELETE', "contacts/{$id}")->assertNoContent();
        $this->assertSame(0, Contact::count());
    }

    #[Test]
    public function tasks_and_transactions_can_be_created_through_the_api(): void
    {
        $contact = Contact::factory()->create();

        $this->api('POST', 'tasks', ['title' => 'Call back', 'type' => 'call', 'priority' => 'normal', 'contact_id' => $contact->id])
            ->assertCreated()->assertJsonPath('data.contact_id', $contact->id);
        $this->api('POST', 'transactions', ['title' => 'Order #1', 'type' => 'sale', 'status' => 'succeeded', 'amount' => 49.5, 'date' => '2026-10-01', 'contact_id' => $contact->id])
            ->assertCreated()->assertJsonPath('data.amount', 49.5);
        $this->api('GET', 'invoices')->assertOk()->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function webhooks_receive_signed_events_and_rest_hooks_can_subscribe(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200), 'zapier.test/*' => Http::response('', 410)]);
        // These hostnames don't resolve, which SafeUrl refuses; SSRF has its own test.
        config(['alp-crm.allow_private_webhooks' => true]);
        $webhook = Webhook::create(['name' => 'All contacts', 'url' => 'https://hooks.example.com/crm', 'events' => ['contact.created', 'contact.tagged']]);
        Webhook::create(['name' => 'Invoices', 'url' => 'https://hooks.example.com/invoices', 'events' => ['invoice.paid']]);

        $subscription = $this->api('POST', 'hooks', ['url' => 'https://zapier.test/hook', 'event' => 'contact.created'])->assertCreated();

        $contact = Contact::factory()->create(['email' => 'maya@example.com']);
        $contact->attachTags(['VIP']);
        app()->terminate();

        Http::assertSent(function ($request) use ($webhook) {
            return $request->url() === 'https://hooks.example.com/crm'
                && $request['event'] === 'contact.created'
                && $request['data']['email'] === 'maya@example.com'
                && $request->header('X-Alp-Signature')[0] === $webhook->sign($request->body());
        });
        Http::assertSent(fn ($request) => $request['event'] === 'contact.tagged' && $request['context']['tags'] === ['VIP']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'invoices'));
        $this->assertSame(200, $webhook->fresh()->last_status);

        // The REST hook answered 410 Gone, so it's removed.
        $this->assertNull(Webhook::find($subscription->json('data.id')));
    }

    #[Test]
    public function only_admins_manage_keys_and_webhooks(): void
    {
        $this->actingAs($this->makeUser('editor@example.com', 'crm_editor'))->get(cp_route('alp-crm.developer'))->assertRedirect();

        $admin = $this->makeUser('super@example.com');
        $admin->makeSuper()->save();

        $this->actingAs($admin)->post(cp_route('alp-crm.developer.keys.store'), ['name' => 'Zapier'])->assertSessionHas('alp_new_api_key');
        $plain = session('alp_new_api_key');
        $this->assertNotNull(ApiKey::findByPlainKey($plain));
        $this->assertDatabaseMissing('crm_api_keys', ['key_hash' => $plain]);

        $this->actingAs($admin)->post(cp_route('alp-crm.developer.webhooks.store'), ['name' => 'Bad', 'url' => 'ftp://x', 'events' => ['contact.created']])->assertSessionHasErrors('url');
        $this->actingAs($admin)->get(cp_route('alp-crm.developer'))->assertOk();
    }

    #[Test]
    public function webhooks_to_private_addresses_are_blocked(): void
    {
        Http::fake();
        $local = Webhook::create(['name' => 'Local', 'url' => 'http://127.0.0.1:6379/', 'events' => ['contact.created']]);
        $metadata = Webhook::create(['name' => 'Metadata', 'url' => 'http://169.254.169.254/latest/meta-data', 'events' => ['contact.created']]);

        Contact::factory()->create();
        app()->terminate();

        Http::assertNothingSent();
        $this->assertStringStartsWith('Blocked', $local->fresh()->last_error);
        $this->assertStringStartsWith('Blocked', $metadata->fresh()->last_error);

        // A host that doesn't resolve is refused too, rather than left to the HTTP client.
        $this->assertFalse(SafeUrl::allowed('http://not-a-real-host.invalid/hook'));
        $this->assertFalse(SafeUrl::allowed('file:///etc/passwd'));

        config(['alp-crm.allow_private_webhooks' => true]);
        $this->assertTrue(SafeUrl::allowed('http://127.0.0.1/'));

        // Redirects are never followed, so a public URL can't bounce to a local one.
        $this->assertFalse(SafeUrl::request('http://127.0.0.1/')->getOptions()['allow_redirects']);

        config(['alp-crm.allow_private_webhooks' => false]);
        $publicRequest = SafeUrl::request('https://8.8.8.8/hook');
        $this->assertNotNull($publicRequest);
        $this->assertSame('', $publicRequest->getOptions()['proxy']);
        $this->assertSame(['8.8.8.8:443:8.8.8.8'], $publicRequest->getOptions()['curl'][CURLOPT_RESOLVE]);
    }
}
