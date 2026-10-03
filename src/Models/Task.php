<?php

namespace RadThemes\AlpCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RadThemes\AlpCrm\Database\Factories\TaskFactory;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Models\Concerns\HasBlueprint;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;

/**
 * A task, call or meeting — optionally linked to a contact or company and shown on the calendar.
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property string $priority
 * @property ?Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property ?Carbon $completed_at
 * @property ?string $assigned_to
 */
class Task extends Model
{
    use HasBlueprint, HasFactory;

    /**
     * Statamic's date-time fields save strings in this format, in the app's timezone.
     */
    private const FIELD_FORMAT = 'Y-m-d H:i';

    protected $table = 'crm_tasks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'all_day' => 'boolean',
            'reminder_minutes' => 'integer',
            'data' => 'array',
        ];
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->created_by ??= User::current()?->id();
        });

        static::saving(function (Task $task) {
            $task->all_day ??= false;

            // Moving the due time re-arms the reminder.
            if ($task->isDirty(['starts_at', 'reminder_minutes'])) {
                $task->reminded_at = null;
            }

            if ($task->company_id === null && $task->contact_id) {
                $task->company_id = Contact::find($task->contact_id)?->company_id;
            }
        });

        static::created(function (Task $task) {
            CrmEvent::fire('task.created', $task);
        });

        static::saved(function (Task $task) {
            if ($task->wasChanged('completed_at') && $task->completed_at && $task->contact) {
                CrmEvent::fire('task.completed', $task);
                $task->contact->logActivity('task_completed', __('Completed “:title”', ['title' => $task->title]), ['task_id' => $task->id]);
            }
        });
    }

    public static function blueprintHandle(): string
    {
        return 'task';
    }

    protected function blueprintColumns(): array
    {
        return ['title', 'type', 'priority', 'description', 'all_day', 'reminder_minutes'];
    }

    protected function relationBlueprintHandles(): array
    {
        return ['starts_at', 'ends_at', 'completed', 'assigned_to', 'contact', 'company'];
    }

    protected function relationBlueprintValues(): array
    {
        return [
            'starts_at' => $this->starts_at?->copy()->setTimezone(config('app.timezone'))->format(self::FIELD_FORMAT),
            'ends_at' => $this->ends_at?->copy()->setTimezone(config('app.timezone'))->format(self::FIELD_FORMAT),
            'completed' => $this->completed_at !== null,
            'assigned_to' => $this->assigned_to ? [$this->assigned_to] : [],
            'contact' => $this->contact_id ? [$this->contact_id] : [],
            'company' => $this->company_id ? [$this->company_id] : [],
        ];
    }

    protected function fillRelationsFromBlueprint(array $values): void
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $values)) {
                $this->{$field} = $values[$field]
                    ? Carbon::createFromFormat(self::FIELD_FORMAT, $values[$field], config('app.timezone'))
                    : null;
            }
        }

        if (array_key_exists('completed', $values)) {
            $this->completed_at = $values['completed'] ? ($this->completed_at ?? now()) : null;
        }

        foreach (['assigned_to' => 'assigned_to', 'contact' => 'contact_id', 'company' => 'company_id'] as $handle => $column) {
            if (array_key_exists($handle, $values)) {
                $this->{$column} = collect($values[$handle])->first();
            }
        }
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee(): ?UserContract
    {
        return $this->assigned_to ? User::find($this->assigned_to) : null;
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->starts_at !== null && $this->starts_at->isPast()
            && ! ($this->all_day && $this->starts_at->isToday());
    }

    public function complete(bool $done = true): void
    {
        $this->update(['completed_at' => $done ? ($this->completed_at ?? now()) : null]);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('starts_at')->where('starts_at', '<', now());
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereNotNull('starts_at')->whereBetween('starts_at', [$from, $to]);
    }

    /**
     * Open tasks whose reminder time has come and that haven't been reminded yet.
     *
     * @return Collection<int, Task>
     */
    public static function dueForReminder(): Collection
    {
        return static::query()
            ->open()
            ->whereNotNull('reminder_minutes')
            ->whereNotNull('starts_at')
            ->whereNull('reminded_at')
            ->where('starts_at', '>', now()->subDay())
            ->get()
            ->filter(fn (Task $task) => $task->starts_at->copy()->subMinutes($task->reminder_minutes)->lte(now()))
            ->values();
    }
}
