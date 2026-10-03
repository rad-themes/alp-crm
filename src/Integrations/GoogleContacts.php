<?php

namespace RadThemes\AlpCrm\Integrations;

use Illuminate\Support\Facades\Http;
use RadThemes\AlpCrm\Capture\LeadCapture;
use RadThemes\AlpCrm\Support\Settings;
use RadThemes\AlpCrm\Support\TokenStore;
use RuntimeException;

/**
 * Imports contacts from a Google account (People API), read-only.
 */
class GoogleContacts
{
    public static function configured(): bool
    {
        return Settings::secret('google_client_id') && Settings::secret('google_client_secret');
    }

    public static function connected(): bool
    {
        return (bool) TokenStore::get('google_refresh_token');
    }

    public static function authorizeUrl(string $redirect, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => Settings::secret('google_client_id'),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/contacts.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public static function connect(string $code, string $redirect): void
    {
        $tokens = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => Settings::secret('google_client_id'),
            'client_secret' => Settings::secret('google_client_secret'),
            'redirect_uri' => $redirect,
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException(__('Google didn’t return a refresh token. Remove the app’s access in your Google account and connect again.'));
        }

        TokenStore::put('google_refresh_token', $tokens['refresh_token']);
    }

    public static function disconnect(): void
    {
        TokenStore::forget('google_refresh_token');
        TokenStore::forget('google_sync_token');
    }

    private static function accessToken(): string
    {
        return (string) Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => Settings::secret('google_client_id'),
            'client_secret' => Settings::secret('google_client_secret'),
            'refresh_token' => TokenStore::get('google_refresh_token') ?? throw new RuntimeException('Google isn’t connected'),
            'grant_type' => 'refresh_token',
        ])->throw()->json('access_token');
    }

    /**
     * Import (or fill in) contacts with an email address. Returns how many were imported or updated.
     */
    public static function import(): int
    {
        $token = self::accessToken();
        $tags = array_merge(['Google Contacts'], (array) Settings::get('google_tags', []));
        $count = 0;
        $pageToken = null;

        do {
            $page = Http::withToken($token)->get('https://people.googleapis.com/v1/people/me/connections', array_filter([
                'personFields' => 'names,emailAddresses,phoneNumbers,organizations',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
            ]))->throw()->json();

            foreach ($page['connections'] ?? [] as $person) {
                $email = $person['emailAddresses'][0]['value'] ?? null;
                if (! $email) {
                    continue;
                }

                $contact = LeadCapture::upsert([
                    'email' => $email,
                    'first_name' => $person['names'][0]['givenName'] ?? null,
                    'last_name' => $person['names'][0]['familyName'] ?? null,
                    'phone' => $person['phoneNumbers'][0]['value'] ?? null,
                    'company' => $person['organizations'][0]['name'] ?? null,
                ], (string) Settings::get('google_status', 'lead'), $tags);

                $count += $contact ? 1 : 0;
            }

            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken);

        TokenStore::put('google_last_import', now()->toIso8601String());

        return $count;
    }
}
