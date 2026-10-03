<?php

namespace RadThemes\AlpCrm\Email;

use RadThemes\AlpCrm\Models\CampaignRecipient;

/**
 * Signed click-tracking and unsubscribe links for campaign emails.
 */
class Tracking
{
    public static function signature(string $token, string $url): string
    {
        return hash_hmac('sha256', $token.'|'.$url, (string) config('app.key'));
    }

    public static function clickUrl(CampaignRecipient $recipient, string $url): string
    {
        return route('statamic.alp-crm.track.click', [
            'token' => $recipient->token,
            'u' => rtrim(strtr(base64_encode($url), '+/', '-_'), '='),
            's' => self::signature($recipient->token, $url),
        ]);
    }

    /**
     * Decode and verify a tracked link, so the redirect can't be abused to send people elsewhere.
     */
    public static function verifiedUrl(string $token, string $encoded, string $signature): ?string
    {
        $url = base64_decode(strtr($encoded, '-_', '+/'), true);

        if (! $url || ! hash_equals(self::signature($token, $url), $signature) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }

    public static function trackLinks(string $html, CampaignRecipient $recipient): string
    {
        return preg_replace_callback(
            '/href="(https?:\/\/[^"]+)"/i',
            fn ($match) => 'href="'.e(self::clickUrl($recipient, html_entity_decode($match[1]))).'"',
            $html,
        );
    }

    public static function unsubscribeUrl(CampaignRecipient $recipient): string
    {
        return route('statamic.alp-crm.unsubscribe', $recipient->token);
    }
}
