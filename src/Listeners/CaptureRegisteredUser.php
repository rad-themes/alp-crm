<?php

namespace RadThemes\AlpCrm\Listeners;

use Illuminate\Support\Str;
use RadThemes\AlpCrm\Capture\LeadCapture;
use RadThemes\AlpCrm\Models\Company;
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

        $joinsKnownCompany = self::companyExists($attributes['company'] ?? null);

        $contact = LeadCapture::upsert($attributes, (string) Settings::get('registration_status', 'lead'), (array) Settings::get('registration_tags', []));

        if (! $contact) {
            return;
        }

        // Nothing a registration form says is verified: neither the email address, which
        // Statamic doesn't confirm, nor the company name, which would hand the new account
        // that company's invoices, quotes and shared files in the portal. So only a contact
        // this registration created, in a company it also created, is linked to the user.
        // Everything else waits for the contact's "Portal user" field in the Control Panel.
        if ($contact->wasRecentlyCreated && ! $joinsKnownCompany) {
            $contact->forceFill(['user_id' => $user->id()])->saveQuietly();
        }

        $contact->logActivity('registered', __('Registered on the site'));
    }

    /**
     * Whether a company LeadCapture would attach this contact to already exists.
     */
    private static function companyExists(mixed $name): bool
    {
        $name = Str::limit(trim((string) $name), 250, '');

        return $name !== '' && Company::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists();
    }
}
