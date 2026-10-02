<?php

namespace RadThemes\RadpackCrm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Support\Documents;
use RadThemes\RadpackCrm\Support\Settings;

/**
 * Emails a quote or invoice to the client, with the PDF attached and a link to view it online.
 */
class DocumentMail extends Mailable
{
    use Queueable;

    public function __construct(public Quote|Invoice $document, public ?string $personalMessage = null) {}

    public function envelope(): Envelope
    {
        $business = Settings::business();
        $isInvoice = $this->document instanceof Invoice;

        return new Envelope(
            subject: $isInvoice
                ? __('Invoice :number from :business', ['number' => $this->document->number, 'business' => $business['name']])
                : __('Quote :number from :business', ['number' => $this->document->number, 'business' => $business['name']]),
            replyTo: $business['email'] ? [$business['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'radpack-crm::mail.document',
            with: [
                'document' => $this->document,
                'isInvoice' => $this->document instanceof Invoice,
                'business' => Settings::business(),
                'url' => Documents::publicUrl($this->document),
                'personalMessage' => $this->personalMessage,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => Documents::pdf($this->document), Documents::filename($this->document))->withMime('application/pdf'),
        ];
    }
}
