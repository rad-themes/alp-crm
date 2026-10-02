<?php

namespace RadThemes\RadpackCrm\Console;

use Illuminate\Console\Command;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Notifications\TaskReminder;
use Statamic\Console\RunsInPlease;

class SendTaskReminders extends Command
{
    use RunsInPlease;

    protected $signature = 'radpack-crm:task-reminders';

    protected $description = 'Email task reminders to assignees (runs every five minutes on the scheduler)';

    public function handle(): int
    {
        $sent = 0;

        foreach (Task::dueForReminder() as $task) {
            if ($assignee = $task->assignee()) {
                $assignee->notify(new TaskReminder($task));
                $sent++;
            }

            $task->forceFill(['reminded_at' => now()])->saveQuietly();
        }

        $this->components->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
