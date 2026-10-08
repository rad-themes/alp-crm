<?php

namespace RadThemes\AlpCrm\Tests\Feature;

use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Tests\TestCase;

class ContactsTest extends TestCase
{
    #[Test]
    public function guests_and_users_without_crm_access_are_kept_out(): void
    {
        $this->get(cp_route('alp-crm.contacts.index'))->assertRedirect();

        $this->actingAs($this->makeUser('nope@example.com', 'no_crm'))
            ->getJson(cp_route('alp-crm.contacts.index'))
            ->assertForbidden();
    }

    #[Test]
    public function viewers_can_browse_but_not_change_contacts(): void
    {
        $contact = Contact::factory()->create();
        $viewer = $this->makeUser('viewer@example.com', 'crm_viewer');

        $this->actingAs($viewer)->get(cp_route('alp-crm.contacts.index'))->assertOk();
        $this->actingAs($viewer)->get(cp_route('alp-crm.contacts.show', $contact))->assertOk();
        $this->actingAs($viewer)->getJson(cp_route('alp-crm.contacts.create'))->assertForbidden();
        $this->actingAs($viewer)->postJson(cp_route('alp-crm.contacts.store'), ['status' => 'lead'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(cp_route('alp-crm.contacts.destroy', $contact))->assertForbidden();
    }

    #[Test]
    public function the_listing_searches_filters_and_sorts(): void
    {
        Contact::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'status' => 'customer']);
        Contact::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.com', 'status' => 'lead']);
        $alias = Contact::factory()->create(['first_name' => 'Alan', 'last_name' => 'Turing', 'status' => 'lead']);
        $alias->aliases()->create(['email' => 'bombe@bletchley.test']);

        $admin = $this->admin();
        $json = fn (array $query) => $this->actingAs($admin)->getJson(cp_route('alp-crm.contacts.json', $query))->assertOk()->json('data.*.name');

        $this->assertSame(['Ada Lovelace', 'Alan Turing', 'Grace Hopper'], $json(['sort' => 'name', 'order' => 'asc']));
        $this->assertSame(['Grace Hopper'], $json(['search' => 'hopper']));
        $this->assertSame(['Alan Turing'], $json(['search' => 'bletchley']));

        $filters = base64_encode(json_encode(['crm_status' => ['status' => ['customer']]]));
        $this->assertSame(['Ada Lovelace'], $json(['filters' => $filters]));
    }

    #[Test]
    public function a_contact_is_created_from_the_blueprint_form(): void
    {
        $company = Company::factory()->create(['name' => 'Analytical Engines']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(cp_route('alp-crm.contacts.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'Ada@Example.com',
            'phone' => '+44 20 7946 0000',
            'status' => 'customer',
            'company' => [$company->id],
            'owner' => [$admin->id()],
            'tags' => ['VIP', 'Mathematics'],
            'aliases' => ['countess@example.com', 'ada@example.com'],
            'city' => 'London',
            'linkedin' => 'https://linkedin.com/in/ada',
        ])->assertOk();

        $contact = Contact::firstOrFail();
        $response->assertJson(['redirect' => cp_route('alp-crm.contacts.show', $contact)]);

        $this->assertSame('Ada', $contact->first_name);
        $this->assertSame('customer', $contact->status);
        $this->assertSame($company->id, $contact->company_id);
        $this->assertSame($admin->id(), $contact->owner_id);
        $this->assertSame(['city' => 'London', 'linkedin' => 'https://linkedin.com/in/ada'], $contact->data);
        $this->assertSame(['Mathematics', 'VIP'], $contact->tags->pluck('name')->all());
        $this->assertSame(['countess@example.com'], $contact->aliases->pluck('email')->all(), 'The main email is not stored as an alias');
        $this->assertSame('created', $contact->activities->first()->event);
        $this->assertTrue(Contact::findByEmail('COUNTESS@example.com')->is($contact));
    }

    #[Test]
    public function validation_comes_from_the_blueprint(): void
    {
        $this->actingAs($this->admin())
            ->postJson(cp_route('alp-crm.contacts.store'), ['email' => 'not-an-email', 'status' => ''])
            ->assertJsonValidationErrors(['email', 'status']);

        $this->assertSame(0, Contact::count());
    }

    #[Test]
    public function an_editor_can_explicitly_link_and_unlink_a_portal_user(): void
    {
        $contact = Contact::factory()->create();
        $client = $this->makeUser('client@example.com');
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson(cp_route('alp-crm.contacts.update', $contact), array_merge($contact->blueprintValues(), [
            'portal_user' => [$client->id()],
        ]))->assertOk();

        $this->assertSame($client->id(), $contact->fresh()->user_id);

        $this->actingAs($admin)->patchJson(cp_route('alp-crm.contacts.update', $contact), array_merge($contact->fresh()->blueprintValues(), [
            'portal_user' => [],
        ]))->assertOk();

        $this->assertNull($contact->fresh()->user_id);
    }

    #[Test]
    public function editing_a_contact_keeps_custom_fields_and_logs_status_changes(): void
    {
        $contact = Contact::factory()->lead()->create(['data' => ['city' => 'Paris', 'favourite_colour' => 'green']]);
        $contact->syncTags(['Old']);

        $this->actingAs($this->admin())->get(cp_route('alp-crm.contacts.edit', $contact))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PublishForm')
                ->where('values.city', 'Paris')
                ->where('values.tags', ['Old']));

        $this->actingAs($this->admin())->patchJson(cp_route('alp-crm.contacts.update', $contact), array_merge($contact->blueprintValues(), [
            'status' => 'customer',
            'tags' => ['New'],
        ]))->assertOk();

        $contact->refresh();
        $this->assertSame('customer', $contact->status);
        $this->assertSame('green', $contact->data['favourite_colour'], 'Values without a blueprint field are kept');
        $this->assertSame(['New'], $contact->tags->pluck('name')->all());
        $this->assertSame('status_changed', $contact->activities->first()->event);
    }

    #[Test]
    public function the_profile_shows_details_notes_and_activity(): void
    {
        $company = Company::factory()->create(['name' => 'Analytical Engines']);
        $contact = Contact::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'company_id' => $company->id, 'data' => ['city' => 'London', 'country' => 'GBR']]);
        $contact->notes()->create(['type' => 'call', 'body' => 'Discussed the engine.']);

        $this->actingAs($this->admin())->get(cp_route('alp-crm.contacts.show', $contact))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('alp-crm::Contacts/Show')
                ->where('contact.name', 'Ada Lovelace')
                ->where('contact.company.name', 'Analytical Engines')
                ->where('notes.0.body', 'Discussed the engine.')
                ->where('details', fn ($details) => collect($details)->pluck('value', 'handle')->only(['city', 'country'])->all() === ['city' => 'London', 'country' => '🇬🇧 United Kingdom'])
                ->has('activities', 1));
    }

    #[Test]
    public function deleting_a_contact_removes_it_and_its_notes(): void
    {
        $contact = Contact::factory()->create();
        $contact->notes()->create(['type' => 'note', 'body' => 'Hi']);
        $contact->syncTags(['VIP']);

        $this->actingAs($this->admin())
            ->delete(cp_route('alp-crm.contacts.destroy', $contact))
            ->assertRedirect(cp_route('alp-crm.contacts.index'));

        $this->assertSame(0, Contact::count());
        $this->assertDatabaseCount('crm_activities', 0);
        $this->assertDatabaseCount('crm_notes', 0);
        $this->assertDatabaseCount('crm_taggables', 0);
    }

    #[Test]
    public function polymorphic_rows_use_stable_aliases_and_old_class_names_are_migrated(): void
    {
        $contact = Contact::factory()->create();
        $contact->syncTags(['VIP']);

        $this->assertSame('crm_contact', \DB::table('crm_activities')->value('subject_type'));
        $this->assertSame('crm_contact', \DB::table('crm_taggables')->value('taggable_type'));

        // Rows written under the former name (Radpack CRM) are converted.
        \DB::table('crm_activities')->update(['subject_type' => 'RadThemes\\RadpackCrm\\Models\\Contact']);
        (require __DIR__.'/../../database/migrations/2026_10_03_000008_use_crm_morph_aliases.php')->up();

        $this->assertSame('crm_contact', \DB::table('crm_activities')->value('subject_type'));
        $this->assertTrue($contact->fresh()->activities()->exists());
    }
}
