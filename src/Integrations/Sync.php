<?php

namespace RadThemes\AlpCrm\Integrations;

use RadThemes\AlpCrm\Integrations\Lists\ListSync;
use RadThemes\AlpCrm\Jobs\SyncContactToLists;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Payments\PayPal;
use RadThemes\AlpCrm\Payments\Stripe;

/**
 * "Sync now" for each integration (also run by the scheduler).
 */
class Sync
{
    /**
     * @return array<string, string>
     */
    public static function services(): array
    {
        return ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'lists' => __('Mailing lists'), 'google' => 'Google Contacts'];
    }

    public static function available(string $service): bool
    {
        return match ($service) {
            'stripe' => Stripe::configured(),
            'paypal' => PayPal::configured(),
            'lists' => (bool) ListSync::active(),
            'google' => GoogleContacts::configured() && GoogleContacts::connected(),
            default => false,
        };
    }

    /**
     * @return string what happened
     */
    public static function run(string $service): string
    {
        return match ($service) {
            'stripe' => __('Imported :count Stripe transactions', ['count' => Stripe::sync()]),
            'paypal' => __('Imported :count PayPal transactions', ['count' => PayPal::sync()]),
            'lists' => __('Queued :count contacts for mailing list sync', ['count' => self::queueAllContacts()]),
            'google' => __('Imported or updated :count contacts from Google', ['count' => GoogleContacts::import()]),
        };
    }

    private static function queueAllContacts(): int
    {
        $count = 0;

        Contact::whereNotNull('email')->select('id')->chunkById(500, function ($contacts) use (&$count) {
            foreach ($contacts as $contact) {
                SyncContactToLists::dispatch($contact->id);
                $count++;
            }
        });

        return $count;
    }
}
