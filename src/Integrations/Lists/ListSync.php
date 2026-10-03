<?php

namespace RadThemes\AlpCrm\Integrations\Lists;

use Illuminate\Support\Str;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Jobs\SyncContactToLists;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Support\Settings;

/**
 * Keeps Mailchimp, Kit and AWeber in step with CRM contacts.
 */
class ListSync
{
    private const EVENTS = ['contact.created', 'contact.updated', 'contact.tagged', 'contact.unsubscribed', 'contact.status_changed'];

    /**
     * @return array<string, class-string<MailingList>>
     */
    public static function services(): array
    {
        return ['mailchimp' => Mailchimp::class, 'kit' => Kit::class, 'aweber' => AWeber::class];
    }

    /**
     * @return array<string, class-string<MailingList>>
     */
    public static function active(): array
    {
        return array_filter(self::services(), fn ($service) => $service::configured());
    }

    /**
     * Only contacts with an email, and the chosen tags (if any).
     */
    public static function shouldSync(Contact $contact): bool
    {
        if (! $contact->email) {
            return false;
        }

        $only = array_map(fn ($tag) => Str::slug($tag), (array) Settings::get('list_sync_tags', []));

        return ! $only || $contact->tags()->whereIn('slug', $only)->exists();
    }

    public function handle(CrmEvent $event): void
    {
        if (! $event->contact || ! in_array($event->name, self::EVENTS, true) || ! self::active()) {
            return;
        }

        config('queue.default') === 'sync'
            ? SyncContactToLists::dispatchAfterResponse($event->contact->id)
            : SyncContactToLists::dispatch($event->contact->id);
    }
}
