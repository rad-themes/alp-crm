<?php

namespace RadThemes\AlpCrm\Integrations\Lists;

use RadThemes\AlpCrm\Models\Contact;

/**
 * An email marketing service the CRM keeps subscribers in sync with.
 */
interface MailingList
{
    public static function label(): string;

    public static function configured(): bool;

    /**
     * Add or update the contact (with their tags), or unsubscribe them if they've opted out.
     */
    public static function sync(Contact $contact): void;
}
