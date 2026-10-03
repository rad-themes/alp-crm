<?php

namespace RadThemes\AlpCrm\Email;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RadThemes\AlpCrm\Models\Campaign;
use RadThemes\AlpCrm\Models\CampaignRecipient;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Email;
use RadThemes\AlpCrm\Support\Settings;
use Throwable;

/**
 * Sends scheduled one-to-one emails and campaigns, in batches, from the scheduler.
 */
class CampaignSender
{
    /**
     * Freeze the audience and start sending.
     */
    public static function start(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return;
        }

        $campaign->audience()->select(['id', 'email'])->chunkById(500, function ($contacts) use ($campaign) {
            CampaignRecipient::insertOrIgnore($contacts
                ->unique(fn (Contact $contact) => mb_strtolower($contact->email))
                ->map(fn (Contact $contact) => [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'email' => mb_strtolower($contact->email),
                    'token' => Str::random(40),
                    'status' => 'queued',
                ])->values()->all());
        });

        $campaign->update(['status' => 'sending', 'started_at' => now()]);
    }

    /**
     * Send up to $limit queued emails. Returns how many were attempted.
     */
    public static function sendBatch(Campaign $campaign, ?int $limit = null): int
    {
        if ($campaign->status !== 'sending') {
            return 0;
        }

        $recipients = $campaign->recipients()->where('status', 'queued')->with('contact')->limit($limit ?? self::batchSize())->get();

        foreach ($recipients as $recipient) {
            $recipient->setRelation('campaign', $campaign);

            if ($recipient->contact && ! $recipient->contact->isSubscribed()) {
                $recipient->update(['status' => 'unsubscribed']);

                continue;
            }

            try {
                Mail::to($recipient->email)->send(new CampaignMail($recipient));
                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $e) {
                $recipient->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500)]);
            }
        }

        if (! $campaign->recipients()->where('status', 'queued')->exists()) {
            $campaign->update(['status' => 'sent', 'finished_at' => now()]);
        }

        return $recipients->count();
    }

    /**
     * One scheduler tick: send due scheduled emails, start due campaigns, and send a batch of each running campaign.
     *
     * @return array{emails: int, campaigns: int}
     */
    public static function tick(): array
    {
        $emails = 0;

        foreach (Email::where('status', 'scheduled')->where('scheduled_at', '<=', now())->with('contact')->get() as $email) {
            EmailSender::deliver($email);
            $emails++;
        }

        Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get()->each(fn (Campaign $campaign) => self::start($campaign));

        $sent = Campaign::where('status', 'sending')->get()->sum(fn (Campaign $campaign) => self::sendBatch($campaign));

        return ['emails' => $emails, 'campaigns' => $sent];
    }

    public static function batchSize(): int
    {
        return max(1, (int) Settings::get('campaign_batch_size', 100));
    }
}
