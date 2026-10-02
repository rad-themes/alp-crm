<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RadThemes\RadpackCrm\Email\Tracking;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Models\CampaignRecipient;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * Public endpoints used by campaign emails: open pixel, click redirect and unsubscribe.
 */
class TrackingController
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $token): Response
    {
        CampaignRecipient::where('token', $token)->whereNull('opened_at')->update(['opened_at' => now()]);

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function click(Request $request, string $token): RedirectResponse
    {
        $url = Tracking::verifiedUrl($token, (string) $request->query('u'), (string) $request->query('s'));

        abort_unless($url, 404);

        if ($recipient = CampaignRecipient::where('token', $token)->first()) {
            $recipient->increment('clicks', 1, [
                'clicked_at' => $recipient->clicked_at ?? now(),
                'opened_at' => $recipient->opened_at ?? now(),
            ]);
        }

        return redirect()->away($url);
    }

    /**
     * A confirmation page (link scanners follow GET links, so GET alone never unsubscribes).
     */
    public function confirm(string $token): Response
    {
        $recipient = CampaignRecipient::with('contact')->where('token', $token)->firstOrFail();

        return response()->view('radpack-crm::unsubscribe', [
            'recipient' => $recipient,
            'done' => $recipient->contact?->unsubscribed_at !== null,
            'business' => Settings::business(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Unsubscribe — from the confirmation page or a mail client's one-click List-Unsubscribe-Post.
     */
    public function unsubscribe(string $token): Response
    {
        $recipient = CampaignRecipient::with(['contact', 'campaign'])->where('token', $token)->firstOrFail();
        $contact = $recipient->contact;

        if ($contact && $contact->unsubscribed_at === null) {
            $contact->forceFill(['unsubscribed_at' => now()])->saveQuietly();
            CrmEvent::fire('contact.unsubscribed', $contact);
            $contact->logActivity('unsubscribed', __('Unsubscribed from emails via “:campaign”', ['campaign' => $recipient->campaign?->name]));
        }

        return response()->view('radpack-crm::unsubscribe', [
            'recipient' => $recipient,
            'done' => true,
            'business' => Settings::business(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
