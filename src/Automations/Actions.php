<?php

namespace RadThemes\RadpackCrm\Automations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RadThemes\RadpackCrm\Email\EmailSender;
use RadThemes\RadpackCrm\Email\MergeTags;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\EmailTemplate;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Support\Payload;
use RadThemes\RadpackCrm\Support\SafeUrl;

/**
 * What an automation can do. Each action returns a short description for the run log,
 * or throws SkipAction when it doesn't apply (e.g. no email address).
 */
class Actions
{
    /**
     * @var array<string, callable(array<string, mixed>, ?Contact, array<string, mixed>): string>
     */
    protected static array $custom = [];

    /**
     * @return array<string, string> type => label
     */
    public static function types(): array
    {
        return array_merge([
            'add_tag' => __('Add tags'),
            'remove_tag' => __('Remove tags'),
            'set_status' => __('Change status'),
            'send_email' => __('Send an email template'),
            'create_task' => __('Create a task'),
            'add_note' => __('Add a note'),
            'notify' => __('Notify the team by email'),
            'webhook' => __('Send to a webhook URL'),
        ], collect(static::$custom)->map(fn ($action) => $action['label'])->all());
    }

    /**
     * Register an extra action, e.g. Actions::extend('send_sms', 'Send an SMS', fn ($action, $contact, $context) => '…').
     */
    public static function extend(string $type, string $label, callable $handler): void
    {
        static::$custom[$type] = ['label' => $label, 'handler' => $handler];
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $context  the triggering event: ['event' => ..., 'payload' => ..., 'context' => ...]
     */
    public static function run(array $action, ?Contact $contact, array $context): string
    {
        $type = $action['type'] ?? '';

        if (isset(static::$custom[$type])) {
            return (static::$custom[$type]['handler'])($action, $contact, $context);
        }

        $needsContact = in_array($type, ['add_tag', 'remove_tag', 'set_status', 'send_email', 'add_note'], true);
        if ($needsContact && ! $contact) {
            throw new SkipAction(__('No contact'));
        }

        return match ($type) {
            'add_tag' => self::addTags($contact, self::list($action['tags'] ?? [])),
            'remove_tag' => self::removeTags($contact, self::list($action['tags'] ?? [])),
            'set_status' => self::setStatus($contact, (string) ($action['status'] ?? '')),
            'send_email' => self::sendEmail($contact, (int) ($action['template_id'] ?? 0)),
            'create_task' => self::createTask($action, $contact),
            'add_note' => self::addNote($contact, (string) ($action['body'] ?? '')),
            'notify' => self::notify($action, $contact, $context),
            'webhook' => self::webhook((string) ($action['url'] ?? ''), $contact, $context),
            default => throw new SkipAction(__('Unknown action “:type”', ['type' => $type])),
        };
    }

    /**
     * @param  array<int, string>  $tags
     */
    private static function addTags(Contact $contact, array $tags): string
    {
        $contact->attachTags($tags);

        return __('Added tags: :tags', ['tags' => implode(', ', $tags)]);
    }

    /**
     * @param  array<int, string>  $tags
     */
    private static function removeTags(Contact $contact, array $tags): string
    {
        $slugs = array_map(fn ($tag) => Str::slug($tag), $tags);
        $contact->tags()->detach($contact->tags()->whereIn('slug', $slugs)->pluck('crm_tags.id'));

        return __('Removed tags: :tags', ['tags' => implode(', ', $tags)]);
    }

    private static function setStatus(Contact $contact, string $status): string
    {
        if ($status === '' || $contact->status === $status) {
            throw new SkipAction(__('Status already :status', ['status' => $status]));
        }

        $contact->update(['status' => $status]);

        return __('Status set to :status', ['status' => $contact->statusLabel($status)]);
    }

    private static function sendEmail(Contact $contact, int $templateId): string
    {
        $template = EmailTemplate::find($templateId) ?? throw new SkipAction(__('The email template was deleted'));

        if (! $contact->email || ! $contact->isSubscribed()) {
            throw new SkipAction($contact->email ? __('Unsubscribed') : __('No email address'));
        }

        $email = EmailSender::compose($contact, $template->subject, $template->body);

        if ($email->status === 'failed') {
            throw new \RuntimeException($email->error ?? __('Sending failed'));
        }

        return __('Emailed “:subject”', ['subject' => $email->subject]);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private static function createTask(array $action, ?Contact $contact): string
    {
        $title = MergeTags::render((string) ($action['title'] ?? __('Follow up')), MergeTags::for($contact));
        $days = (int) ($action['due_in_days'] ?? 1);

        Task::create([
            'title' => Str::limit($title, 250, ''),
            'type' => $action['task_type'] ?? 'task',
            'priority' => $action['priority'] ?? 'normal',
            'starts_at' => now()->addDays($days)->setTime(9, 0),
            'contact_id' => $contact?->id,
            'assigned_to' => $action['assign_to'] ?? $contact?->owner_id,
        ]);

        return __('Created task “:title”', ['title' => $title]);
    }

    private static function addNote(Contact $contact, string $body): string
    {
        $contact->notes()->create(['type' => 'note', 'body' => MergeTags::render($body, MergeTags::for($contact))]);

        return __('Added a note');
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $context
     */
    private static function notify(array $action, ?Contact $contact, array $context): string
    {
        $to = array_filter(self::list($action['to'] ?? []), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if (! $to) {
            throw new SkipAction(__('No recipients'));
        }

        $subject = MergeTags::render((string) ($action['subject'] ?? __('CRM: :event', ['event' => $context['event'] ?? ''])), MergeTags::for($contact));
        $lines = array_filter([
            MergeTags::render((string) ($action['message'] ?? ''), MergeTags::for($contact)),
            $contact ? __('Contact: :name <:email>', ['name' => $contact->name(), 'email' => $contact->email]) : null,
            $contact ? cp_route('radpack-crm.contacts.show', $contact) : null,
        ]);

        Mail::raw(implode("\n\n", $lines), fn ($message) => $message->to($to)->subject($subject));

        return __('Notified :to', ['to' => implode(', ', $to)]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function webhook(string $url, ?Contact $contact, array $context): string
    {
        if (! SafeUrl::allowed($url)) {
            throw new SkipAction(__('The URL is invalid or points to a private address'));
        }

        $response = Http::timeout(10)->post($url, [
            'event' => $context['event'] ?? null,
            'data' => $context['payload'] ?? null,
            'contact' => $contact ? Payload::contact($contact) : null,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(__('The URL answered :status', ['status' => $response->status()]));
        }

        return __('Posted to :host', ['host' => parse_url($url, PHP_URL_HOST)]);
    }

    /**
     * @return array<int, string>
     */
    private static function list(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[,\n]/', (string) $value);

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $items)));
    }
}
