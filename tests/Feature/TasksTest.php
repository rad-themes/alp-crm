<?php

namespace RadThemes\RadpackCrm\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Notifications\TaskReminder;
use RadThemes\RadpackCrm\Tests\TestCase;

class TasksTest extends TestCase
{
    #[Test]
    public function a_task_is_created_from_the_blueprint_with_the_right_time_in_any_timezone(): void
    {
        config(['app.timezone' => 'America/New_York']);
        date_default_timezone_set('America/New_York');

        $contact = Contact::factory()->create(['company_id' => Company::factory()->create()->id]);
        $admin = $this->admin();

        // The CP sends date-times as UTC.
        $this->actingAs($admin)->postJson(cp_route('radpack-crm.tasks.store'), [
            'title' => 'Call about the proposal',
            'type' => 'call',
            'priority' => 'high',
            'starts_at' => '2026-10-05T14:30:00.000Z',
            'reminder_minutes' => '60',
            'assigned_to' => [$admin->id()],
            'contact' => [$contact->id],
        ])->assertOk()->assertJson(['redirect' => cp_route('radpack-crm.contacts.show', $contact)]);

        $task = Task::firstOrFail();
        $this->assertSame('2026-10-05 10:30', $task->starts_at->format('Y-m-d H:i'), '14:30 UTC is 10:30 in New York');
        $this->assertSame(60, $task->reminder_minutes);
        $this->assertSame($contact->company_id, $task->company_id, 'The company is taken from the contact');
        $this->assertSame($admin->id(), $task->assigned_to);

        // Editing round-trips the same instant.
        $this->actingAs($admin)->get(cp_route('radpack-crm.tasks.edit', $task))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('PublishForm')->where('values.starts_at', '2026-10-05T14:30:00.000Z'));

        date_default_timezone_set('UTC');
    }

    #[Test]
    public function tasks_can_be_completed_and_reopened(): void
    {
        $contact = Contact::factory()->create();
        $task = Task::factory()->create(['contact_id' => $contact->id, 'title' => 'Send contract']);

        $this->actingAs($this->admin())->post(cp_route('radpack-crm.tasks.toggle', $task), ['done' => true])->assertRedirect();
        $this->assertTrue($task->fresh()->isDone());
        $this->assertSame('Completed “Send contract”', $contact->activities()->first()->description);

        $this->actingAs($this->admin())->post(cp_route('radpack-crm.tasks.toggle', $task), ['done' => false]);
        $this->assertFalse($task->fresh()->isDone());
    }

    #[Test]
    public function the_task_list_filters_by_view(): void
    {
        $admin = $this->admin();
        Task::factory()->create(['title' => 'Mine', 'assigned_to' => $admin->id()]);
        Task::factory()->create(['title' => 'Theirs', 'assigned_to' => 'someone-else']);
        Task::factory()->overdue()->create(['title' => 'Late']);
        Task::factory()->done()->create(['title' => 'Finished']);

        $titles = fn (string $view) => $this->actingAs($admin)->getJson(cp_route('radpack-crm.tasks.json', ['view' => $view]))->json('data.*.title');

        $this->assertSame(['Mine'], $titles('mine'));
        $this->assertEqualsCanonicalizing(['Mine', 'Theirs', 'Late'], $titles('open'));
        $this->assertSame(['Late'], $titles('overdue'));
        $this->assertSame(['Finished'], $titles('done'));
        $this->assertCount(4, $titles('all'));
    }

    #[Test]
    public function reminders_are_emailed_once_when_due(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-10-05 09:00:00');
        $admin = $this->admin();

        $due = Task::factory()->create(['starts_at' => '2026-10-05 09:30:00', 'reminder_minutes' => 60, 'assigned_to' => $admin->id()]);
        Task::factory()->create(['starts_at' => '2026-10-05 12:00:00', 'reminder_minutes' => 60, 'assigned_to' => $admin->id()]);
        Task::factory()->done()->create(['starts_at' => '2026-10-05 09:30:00', 'reminder_minutes' => 60, 'assigned_to' => $admin->id()]);
        Task::factory()->create(['starts_at' => '2026-10-05 09:30:00', 'reminder_minutes' => null, 'assigned_to' => $admin->id()]);

        $this->artisan('radpack-crm:task-reminders')->assertSuccessful();
        $this->artisan('radpack-crm:task-reminders')->assertSuccessful();

        Notification::assertSentToTimes($admin, TaskReminder::class, 1);
        Notification::assertSentTo($admin, TaskReminder::class, fn (TaskReminder $reminder) => $reminder->task->is($due));

        // Moving the task re-arms the reminder.
        $this->assertNotNull($due->fresh()->reminded_at);
        $due->fresh()->update(['starts_at' => '2026-10-05 09:45:00']);
        $this->assertNull($due->fresh()->reminded_at);

        Carbon::setTestNow();
    }

    #[Test]
    public function the_reminder_email_renders(): void
    {
        $task = Task::factory()->create(['title' => 'Kick-off call', 'type' => 'call', 'contact_id' => Contact::factory()->create(['first_name' => 'Ada', 'last_name' => 'L'])->id]);

        $html = (string) (new TaskReminder($task))->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('Kick-off call', html_entity_decode($html));
        $this->assertStringContainsString('With Ada L', html_entity_decode($html));
    }

    #[Test]
    public function the_calendar_shows_tasks_and_invoice_due_dates_for_the_month(): void
    {
        $admin = $this->admin();
        Task::factory()->create(['title' => 'Workshop', 'starts_at' => '2026-10-14 10:00:00', 'assigned_to' => $admin->id()]);
        Task::factory()->create(['title' => 'Other month', 'starts_at' => '2026-12-01 10:00:00']);
        Task::factory()->create(['title' => 'Someone else', 'starts_at' => '2026-10-15 10:00:00', 'assigned_to' => 'other']);
        Invoice::factory()->sent()->withItems()->create(['number' => 'INV-0042', 'due_date' => '2026-10-20']);

        $this->actingAs($admin)->get(cp_route('radpack-crm.calendar', ['month' => '2026-10']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('radpack-crm::Calendar')
                ->where('events.2026-10-14.0.title', 'Workshop')
                ->where('events.2026-10-20.0.title', 'INV-0042 due')
                ->has('events.2026-10-15')
                ->missing('events.2026-12-01'));

        $this->actingAs($admin)->get(cp_route('radpack-crm.calendar', ['month' => '2026-10', 'mine' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('events.2026-10-15'));

        $this->actingAs($admin)->get(cp_route('radpack-crm.calendar', ['month' => 'nonsense']))->assertOk();
    }

    #[Test]
    public function contact_profiles_and_the_dashboard_list_tasks(): void
    {
        $admin = $this->admin();
        $contact = Contact::factory()->create();
        Task::factory()->create(['contact_id' => $contact->id, 'title' => 'Follow up', 'assigned_to' => $admin->id(), 'starts_at' => now()->addDay()]);

        $this->actingAs($admin)->get(cp_route('radpack-crm.contacts.show', $contact))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tasks.0.title', 'Follow up'));

        $this->actingAs($admin)->get(cp_route('radpack-crm.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('myTasks.0.title', 'Follow up'));
    }

    #[Test]
    public function deleting_a_contact_or_company_keeps_their_tasks_and_sales_records(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);
        $task = Task::factory()->create(['contact_id' => $contact->id]);
        $invoice = Invoice::factory()->create(['contact_id' => $contact->id, 'company_id' => $company->id]);

        $contact->delete();
        $this->assertNull($task->fresh()->contact_id);
        $this->assertNull($invoice->fresh()->contact_id);

        $company->delete();
        $this->assertNull($task->fresh()->company_id);
        $this->assertNull($invoice->fresh()->company_id);
    }

    #[Test]
    public function the_complete_bulk_action_and_permissions(): void
    {
        $tasks = Task::factory()->count(2)->create();

        $this->actingAs($this->admin())->postJson(cp_route('radpack-crm.tasks.actions.run'), [
            'action' => 'crm_complete_tasks', 'selections' => $tasks->pluck('id')->all(), 'values' => [],
        ])->assertOk();

        $this->assertSame(2, Task::whereNotNull('completed_at')->count());

        $this->actingAs($this->makeUser('viewer@example.com', 'crm_viewer'))
            ->postJson(cp_route('radpack-crm.tasks.toggle', $tasks->first()), ['done' => false])
            ->assertForbidden();
    }
}
