<?php

namespace RadThemes\RadpackCrm\Integrations;

use Illuminate\Support\Facades\Http;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Support\Settings;
use RuntimeException;

/**
 * Text messages through Twilio, logged as "SMS" notes on the contact.
 */
class Twilio
{
    public static function configured(): bool
    {
        return Settings::secret('twilio_sid') && Settings::secret('twilio_token') && Settings::get('twilio_from');
    }

    public static function send(Contact $contact, string $body): void
    {
        if (! self::configured()) {
            throw new RuntimeException(__('Twilio isn’t set up.'));
        }

        $to = self::normalise((string) $contact->phone);

        if (! $to) {
            throw new RuntimeException(__('This contact has no phone number.'));
        }

        $sid = (string) Settings::secret('twilio_sid');
        $response = Http::asForm()->withBasicAuth($sid, (string) Settings::secret('twilio_token'))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => Settings::get('twilio_from'),
                'Body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?? __('Twilio error :status', ['status' => $response->status()]));
        }

        $contact->notes()->create(['type' => 'sms', 'body' => $body]);
        $contact->forceFill(['last_contacted_at' => now()])->saveQuietly();
    }

    /**
     * E.164-ish: keep a leading +, digits only; add the default country code to local numbers.
     */
    public static function normalise(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with(trim($phone), '+')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        $prefix = preg_replace('/\D+/', '', (string) Settings::get('twilio_country_code', ''));

        return $prefix ? '+'.$prefix.ltrim($digits, '0') : '+'.$digits;
    }
}
