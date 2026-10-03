<?php

namespace RadThemes\AlpCrm\Tests\Feature;

use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Note;
use RadThemes\AlpCrm\Tests\TestCase;

class CompaniesNotesActionsTest extends TestCase
{
    #[Test]
    public function companies_can_be_created_listed_and_shown_with_their_contacts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(cp_route('alp-crm.companies.store'), [
            'name' => 'Globex',
            'status' => 'customer',
            'website' => 'https://globex.test',
            'tags' => ['Enterprise'],
        ])->assertOk();

        $company = Company::firstOrFail();
        Contact::factory()->count(2)->create(['company_id' => $company->id]);

        $this->actingAs($admin)->getJson(cp_route('alp-crm.companies.json'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Globex')
            ->assertJsonPath('data.0.contacts_count', 2);

        $this->actingAs($admin)->get(cp_route('alp-crm.companies.show', $company))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('alp-crm::Companies/Show')
                ->where('company.tags', ['Enterprise'])
                ->has('contacts', 2));
    }

    #[Test]
    public function company_names_are_required(): void
    {
        $this->actingAs($this->admin())
            ->postJson(cp_route('alp-crm.companies.store'), ['status' => 'lead'])
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function deleting_a_company_keeps_its_contacts(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->actingAs($this->admin())->delete(cp_route('alp-crm.companies.destroy', $company))->assertRedirect(cp_route('alp-crm.companies.index'));

        $this->assertSame(0, Company::count());
        $this->assertNull($contact->fresh()->company_id);
    }

    #[Test]
    public function calls_are_logged_and_update_last_contacted(): void
    {
        $contact = Contact::factory()->create();
        $editor = $this->makeUser('editor@example.com', 'crm_editor');

        $this->actingAs($editor)
            ->post(cp_route('alp-crm.notes.store', ['contact', $contact->id]), ['type' => 'call', 'body' => 'Followed up on the quote.'])
            ->assertRedirect();

        $note = Note::firstOrFail();
        $this->assertSame($editor->id(), $note->user_id);
        $this->assertNotNull($contact->fresh()->last_contacted_at);
        $this->assertSame('note_added', $contact->activities()->first()->event);

        $this->actingAs($editor)->delete(cp_route('alp-crm.notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function notes_are_validated_and_permission_checked(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin())
            ->post(cp_route('alp-crm.notes.store', ['company', $company->id]), ['type' => 'telepathy', 'body' => ''])
            ->assertSessionHasErrors(['type', 'body']);

        $this->actingAs($this->makeUser('viewer@example.com', 'crm_viewer'))
            ->postJson(cp_route('alp-crm.notes.store', ['company', $company->id]), ['type' => 'note', 'body' => 'Hi'])
            ->assertForbidden();
    }

    #[Test]
    public function bulk_actions_tag_change_status_and_delete(): void
    {
        $contacts = Contact::factory()->count(3)->lead()->create();
        $ids = $contacts->pluck('id')->all();
        $admin = $this->admin();
        $run = fn (string $action, array $values = [], ?array $selections = null) => $this->actingAs($admin)->postJson(
            cp_route('alp-crm.contacts.actions.run'),
            ['action' => $action, 'selections' => $selections ?? $ids, 'values' => $values],
        );

        $run('crm_add_tags', ['tags' => ['Newsletter']])->assertOk();
        $this->assertSame(3, Contact::withTag('Newsletter')->count());

        $run('crm_change_status', ['status' => 'customer'])->assertOk();
        $this->assertSame(3, Contact::where('status', 'customer')->count());

        $run('crm_delete', [], [$ids[0]])->assertOk();
        $this->assertSame(2, Contact::count());
    }

    #[Test]
    public function editors_cannot_run_the_delete_action(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAs($this->makeUser('editor@example.com', 'crm_editor'))
            ->postJson(cp_route('alp-crm.contacts.actions.run'), ['action' => 'crm_delete', 'selections' => [$contact->id], 'values' => []])
            ->assertForbidden();

        $this->assertSame(1, Contact::count());
    }

    #[Test]
    public function the_company_picker_lists_and_searches_companies(): void
    {
        Company::factory()->create(['name' => 'Acme']);
        Company::factory()->create(['name' => 'Globex']);

        $this->actingAs($this->admin())
            ->getJson(cp_route('relationship.index', ['config' => base64_encode(json_encode(['type' => 'crm_companies'])), 'search' => 'glob']))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Globex')
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function the_dashboard_summarises_the_crm(): void
    {
        Company::factory()->create();
        Contact::factory()->count(2)->lead()->create();
        Contact::factory()->customer()->create();

        $this->actingAs($this->admin())->get(cp_route('alp-crm.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('alp-crm::Dashboard')
                ->where('stats.0.value', 3)
                ->where('stats.1.value', 1)
                ->where('statuses.0', ['value' => 'lead', 'label' => 'Lead', 'total' => 2])
                ->has('recentContacts', 3)
                ->has('activity', 4));

        $this->actingAs($this->admin())->get(cp_route('alp-crm.home'))->assertRedirect(cp_route('alp-crm.dashboard'));
    }

    #[Test]
    public function every_listing_returns_its_columns_for_the_listing_component(): void
    {
        $admin = $this->admin();

        foreach (['contacts', 'companies', 'quotes', 'invoices', 'transactions', 'tasks'] as $listing) {
            $this->actingAs($admin)->getJson(cp_route("alp-crm.{$listing}.json", ['columns' => 'title,name,number,date']))
                ->assertOk()
                ->assertJsonStructure(['meta' => ['columns' => [['field', 'label', 'sortable', 'visible']]]]);
        }

        $columns = collect($this->actingAs($admin)->getJson(cp_route('alp-crm.contacts.json', ['columns' => 'name,phone']))->json('meta.columns'))->pluck('visible', 'field');
        $this->assertTrue($columns['phone']);
        $this->assertFalse($columns['email']);
    }

    #[Test]
    public function only_http_links_are_made_clickable_on_profiles(): void
    {
        $contact = Contact::factory()->create(['data' => ['website' => 'javascript:alert(document.cookie)', 'linkedin' => 'https://linkedin.com/in/maya']]);

        $details = collect($this->actingAs($this->admin())->get(cp_route('alp-crm.contacts.show', $contact))->viewData('page')['props']['details'])->keyBy('label');

        $this->assertNull($details['Website']['url']);
        $this->assertSame('https://linkedin.com/in/maya', $details['LinkedIn']['url']);
    }
}
