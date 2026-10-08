<?php

namespace RadThemes\AlpCrm\Listeners;

use RadThemes\AlpCrm\Capture\LeadCapture;
use RadThemes\AlpCrm\Support\Settings;
use Statamic\Events\UserRegistered;

/**
 * New site registrations → CRM contacts.
 */
class CaptureRegisteredUser
{
    public function handle(UserRegistered $event): void
    {
        if (! Settings::get('capture_registrations', false)) {
            return;
        }

        $user = $event->user;

        [$attributes] = LeadCapture::map(array_merge($user->data()->all(), ['email' => $user->email(), 'name' => $user->name()]));

        $contact = LeadCapture::upsert($attributes, (string) Settings::get('registration_status', 'lead'), (array) Settings::get('registration_tags', []));

        if (! $contact) {
            return;
        }

        // Only a contact this registration created is linked to the user. An existing
        // contact is left alone: Statamic doesn't verify email addresses, so anyone could
        // register with a client's address and inherit their portal billing. Linking those
        // is a deliberate act — the contact's "Portal user" field in the Control Panel.
        if ($contact->wasRecentlyCreated) {
            $contact->forceFill(['user_id' => $user->id()])->saveQuietly();
        }

        $contact->logActivity('registered', __('Registered on the site'));
    }
}
