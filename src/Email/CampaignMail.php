<?php

namespace RadThemes\AlpCrm\Email;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use RadThemes\AlpCrm\Models\CampaignRecipient;
use RadThemes\AlpCrm\Support\Settings;

/**
 * A campaign email to one recipient: personalised, with tracked links, an open pixel and an unsubscribe link.
 */
class CampaignMail extends Mailable
{
    use Queueable;

    public function __construct(public CampaignRecipient $recipient) {}

    public function envelope(): Envelope
    {
        $business = Settings::business();

        return new Envelope(
            subject: MergeTags::render($this->recipient->campaign->subject, MergeTags::for($this->recipient->contact)),
            replyTo: $business['email'] ? [$business['email']] : [],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.Tracking::unsubscribeUrl($this->recipient).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $markdown = MergeTags::render($this->recipient->campaign->body, MergeTags::for($this->recipient->contact));

        return new Content(
            view: 'alp-crm::mail.layout',
            with: [
                'html' => Tracking::trackLinks(MergeTags::html($markdown), $this->recipient),
                'business' => Settings::business(),
                'unsubscribeUrl' => Tracking::unsubscribeUrl($this->recipient),
                'pixelUrl' => route('statamic.alp-crm.track.open', $this->recipient->token),
            ],
        );
    }
}
