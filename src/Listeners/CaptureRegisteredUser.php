<?php

namespace RadThemes\RadpackCrm\Listeners;

use RadThemes\RadpackCrm\Capture\LeadCapture;
use RadThemes\RadpackCrm\Support\Settings;
use Statamic\Events\UserRegistered;

/**
 * New site registrations → CRM contacts, linked to the user.
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

        if ($contact) {
            $contact->forceFill(['user_id' => $user->id()])->saveQuietly();
            $contact->logActivity('registered', __('Registered on the site'));
        }
    }
}
