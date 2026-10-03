<?php

namespace RadThemes\AlpCrm\Tests\Feature;

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Tests\TestCase;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;

class CustomFieldsTest extends TestCase
{
    private string $blueprints;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulate a site owner adding a "Lead source" field in the blueprint editor (saved as a vendor override).
        $this->blueprints = sys_get_temp_dir().'/alp-crm-blueprints-'.uniqid();
        $contact = YAML::file(__DIR__.'/../../resources/blueprints/contact.yaml')->parse();
        $contact['tabs']['main']['sections'][0]['fields'][] = [
            'handle' => 'lead_source',
            'field' => ['type' => 'select', 'display' => 'Lead source', 'options' => ['web' => 'Website', 'event' => 'Event']],
        ];
        File::ensureDirectoryExists($this->blueprints.'/vendor/alp-crm');
        File::put($this->blueprints.'/vendor/alp-crm/contact.yaml', YAML::dump($contact));
        Blueprint::setDirectory($this->blueprints);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->blueprints);

        parent::tearDown();
    }

    #[Test]
    public function custom_blueprint_fields_are_saved_and_shown_on_the_profile(): void
    {
        $this->actingAs($this->admin())->postJson(cp_route('alp-crm.contacts.store'), [
            'first_name' => 'Ada',
            'status' => 'lead',
            'lead_source' => 'event',
        ])->assertOk();

        $contact = Contact::firstOrFail();
        $this->assertSame('event', $contact->data['lead_source']);

        $this->actingAs($this->admin())->get(cp_route('alp-crm.contacts.show', $contact))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('details', fn ($details) => collect($details)->firstWhere('handle', 'lead_source')['value'] === 'Event'));
    }

    #[Test]
    public function values_that_are_not_in_the_blueprint_are_ignored(): void
    {
        $this->actingAs($this->admin())->postJson(cp_route('alp-crm.contacts.store'), [
            'first_name' => 'Ada',
            'status' => 'lead',
            'owner_id' => 'someone-else',
            'secret_flag' => true,
            'id' => 999,
        ])->assertOk();

        $contact = Contact::firstOrFail();
        $this->assertNotSame(999, $contact->id);
        $this->assertNull($contact->owner_id);
        $this->assertArrayNotHasKey('secret_flag', (array) $contact->data);
    }
}
