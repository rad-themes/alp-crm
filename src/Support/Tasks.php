<?php

namespace RadThemes\AlpCrm\Support;

use RadThemes\AlpCrm\Models\Task;

class Tasks
{
    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'task' => __('Task'),
            'call' => __('Call'),
            'meeting' => __('Meeting'),
            'email' => __('Email'),
            'deadline' => __('Deadline'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'type' => $task->type,
            'type_label' => self::typeLabels()[$task->type] ?? ucfirst($task->type),
            'priority' => $task->priority,
            'starts_at' => $task->starts_at?->toIso8601String(),
            'ends_at' => $task->ends_at?->toIso8601String(),
            'all_day' => $task->all_day,
            'done' => $task->isDone(),
            'overdue' => $task->isOverdue(),
            'assignee' => $task->assignee()?->name(),
            'contact' => $task->contact?->name(),
            'contact_url' => $task->contact ? cp_route('alp-crm.contacts.show', $task->contact) : null,
            'company' => $task->company?->name,
            'edit_url' => cp_route('alp-crm.tasks.edit', $task),
            'toggle_url' => cp_route('alp-crm.tasks.toggle', $task),
        ];
    }
}
