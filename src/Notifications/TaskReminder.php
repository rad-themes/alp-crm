<?php

namespace RadThemes\AlpCrm\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RadThemes\AlpCrm\Models\Task;
use RadThemes\AlpCrm\Support\Tasks;

class TaskReminder extends Notification
{
    public function __construct(public Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->task->all_day
            ? $this->task->starts_at->isoFormat('LL')
            : $this->task->starts_at->isoFormat('LLL');

        return (new MailMessage)
            ->subject(__('Reminder: :title', ['title' => $this->task->title]))
            ->line(__(':type “:title” is due :when.', [
                'type' => Tasks::typeLabels()[$this->task->type] ?? __('Task'),
                'title' => $this->task->title,
                'when' => $when,
            ]))
            ->when($this->task->contact, fn (MailMessage $message) => $message->line(__('With :name', ['name' => $this->task->contact->name()])))
            ->when($this->task->description, fn (MailMessage $message) => $message->line($this->task->description))
            ->action(__('Open task'), cp_route('alp-crm.tasks.edit', $this->task));
    }
}
