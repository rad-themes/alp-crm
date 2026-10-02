<?php

namespace RadThemes\RadpackCrm\Email;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * A one-to-one email to a contact, written in the CRM.
 */
class ContactMail extends Mailable
{
    use Queueable;

    public function __construct(public string $mailSubject, public string $body) {}

    public function envelope(): Envelope
    {
        $business = Settings::business();

        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $business['email'] ? [$business['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'radpack-crm::mail.layout',
            with: [
                'html' => MergeTags::html($this->body),
                'business' => Settings::business(),
                'unsubscribeUrl' => null,
                'pixelUrl' => null,
            ],
        );
    }
}
