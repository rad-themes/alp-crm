<?php

namespace RadThemes\AlpCrm\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\File;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Password;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Portal\PortalPages;
use RadThemes\AlpCrm\Tests\TestCase;

class ClientFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->setTestRoles([
            'crm_admin' => ['access cp', 'view crm', 'edit crm', 'delete crm'],
            'crm_vault' => ['access cp', 'view crm', 'edit crm', 'manage crm passwords'],
        ]);
    }

    #[Test]
    public function files_are_uploaded_to_a_profile_downloaded_and_deleted(): void
    {
        $contact = Contact::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(cp_route('alp-crm.files.store', ['contact', $contact->id]), [
            'files' => [UploadedFile::fake()->createWithContent('brief.pdf', 'PDF!'), UploadedFile::fake()->createWithContent('logo.svg', '<svg/>')],
            'portal' => true,
        ])->assertRedirect()->assertSessionHas('success', 'Uploaded 2 files');

        $file = File::where('name', 'brief.pdf')->sole();
        $this->assertTrue($file->portal);
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith("alp-crm/files/contacts/{$contact->id}/", $file->path);

        $download = $this->actingAs($admin)->get(cp_route('alp-crm.files.download', $file))->assertOk();
        $this->assertSame('PDF!', $download->streamedContent());
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));

        $this->actingAs($admin)->delete(cp_route('alp-crm.files.destroy', $file));
        Storage::disk('local')->assertMissing($file->path);

        // Deleting the contact deletes the rest of their files.
        $other = File::sole();
        $contact->delete();
        Storage::disk('local')->assertMissing($other->path);
        $this->assertSame(0, File::count());
    }

    #[Test]
    public function passwords_are_encrypted_and_reveals_are_logged(): void
    {
        $company = Company::factory()->create();
        $vault = $this->makeUser('vault@example.com', 'crm_vault');

        $this->actingAs($this->admin())->postJson(cp_route('alp-crm.passwords.store', ['company', $company->id]), ['label' => 'Hosting'])->assertForbidden();

        $this->actingAs($vault)->post(cp_route('alp-crm.passwords.store', ['company', $company->id]), [
            'label' => 'Hosting', 'url' => 'https://host.example.com', 'username' => 'acme', 'password' => 's3cret!', 'notes' => 'PIN 1234',
        ])->assertRedirect();

        $password = Password::sole();
        $raw = \DB::table('crm_passwords')->first();
        $this->assertStringNotContainsString('s3cret', $raw->password);
        $this->assertStringNotContainsString('1234', $raw->notes);

        $this->actingAs($vault)->get(cp_route('alp-crm.companies.show', $company))
            ->assertInertia(fn ($page) => $page->where('passwords.0.label', 'Hosting')->missing('passwords.0.password'));
        $this->actingAs($this->admin())->get(cp_route('alp-crm.companies.show', $company))
            ->assertInertia(fn ($page) => $page->where('passwords', null));

        $this->actingAs($vault)->postJson(cp_route('alp-crm.passwords.reveal', $password))->assertOk()->assertJson(['password' => 's3cret!', 'notes' => 'PIN 1234']);
        $this->assertSame('password_viewed', $company->activities()->latest('id')->first()->event);

        // Leaving the password empty keeps it.
        $this->actingAs($vault)->patch(cp_route('alp-crm.passwords.update', $password), ['label' => 'Hosting panel', 'password' => '']);
        $this->assertSame(['Hosting panel', 's3cret!'], [$password->fresh()->label, $password->fresh()->password]);
    }

    #[Test]
    public function portal_clients_only_see_their_own_documents_and_shared_files(): void
    {
        $company = Company::factory()->create();
        $maya = Contact::factory()->create(['email' => 'maya@example.com', 'company_id' => $company->id]);
        $colleague = Contact::factory()->create(['company_id' => $company->id]);
        $stranger = Contact::factory()->create();
        $user = $this->makeUser('MAYA@example.com');

        $mine = Invoice::factory()->create(['contact_id' => $maya->id, 'status' => 'sent', 'total' => 120]);
        $companyInvoice = Invoice::factory()->create(['contact_id' => $colleague->id, 'company_id' => $company->id, 'status' => 'paid']);
        Invoice::factory()->create(['contact_id' => $maya->id, 'status' => 'draft']);
        Invoice::factory()->create(['contact_id' => $stranger->id, 'status' => 'sent']);
        Quote::factory()->create(['contact_id' => $maya->id, 'status' => 'sent']);

        $this->assertEqualsCanonicalizing([$mine->id, $companyInvoice->id], PortalPages::documents(Invoice::query(), $user)->pluck('id')->all());
        $this->assertSame(1, PortalPages::documents(Quote::query(), $user)->count());

        $shared = File::create(['contact_id' => $maya->id, 'name' => 'contract.pdf', 'disk' => 'local', 'path' => 'alp-crm/files/a.pdf', 'portal' => true]);
        $private = File::create(['contact_id' => $maya->id, 'name' => 'notes.pdf', 'disk' => 'local', 'path' => 'alp-crm/files/b.pdf', 'portal' => false]);
        $theirs = File::create(['contact_id' => $stranger->id, 'name' => 'x.pdf', 'disk' => 'local', 'path' => 'alp-crm/files/c.pdf', 'portal' => true]);
        Storage::disk('local')->put('alp-crm/files/a.pdf', 'contract');

        $this->get(route('statamic.alp-crm.portal.file', $shared))->assertForbidden();
        $this->actingAs($user)->get(route('statamic.alp-crm.portal.file', $shared))->assertOk();
        $this->actingAs($user)->get(route('statamic.alp-crm.portal.file', $private))->assertNotFound();
        $this->actingAs($user)->get(route('statamic.alp-crm.portal.file', $theirs))->assertNotFound();

        $html = view('alp-crm::portal.billing', ['invoices' => collect([$mine]), 'quotes' => collect(), 'payments' => collect()])->render();
        $this->assertStringContainsString($mine->number, $html);
        $this->assertStringContainsString('View &amp; pay', $html);
    }
}
