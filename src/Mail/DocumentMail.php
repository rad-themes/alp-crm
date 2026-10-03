<?php

namespace RadThemes\AlpCrm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use RadThemes\AlpCrm\Email\MergeTags;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Support\Documents;
use RadThemes\AlpCrm\Support\Settings;

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

        $custom = Settings::raw(($isInvoice ? 'invoice' : 'quote').'_email_subject');

        return new Envelope(
            subject: $custom ? MergeTags::render((string) $custom, $this->mergeTags()) : ($isInvoice
                ? __('Invoice :number from :business', ['number' => $this->document->number, 'business' => $business['name']])
                : __('Quote :number from :business', ['number' => $this->document->number, 'business' => $business['name']])),
            replyTo: $business['email'] ? [$business['email']] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'alp-crm::mail.document',
            with: [
                'document' => $this->document,
                'isInvoice' => $this->document instanceof Invoice,
                'business' => Settings::business(),
                'url' => Documents::publicUrl($this->document),
                'introMessage' => $this->personalMessage ?: $this->defaultMessage(),
            ],
        );
    }

    private function defaultMessage(): ?string
    {
        $template = Settings::raw(($this->document instanceof Invoice ? 'invoice' : 'quote').'_email_message');

        return $template ? MergeTags::render((string) $template, $this->mergeTags()) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeTags(): array
    {
        $document = $this->document;

        return [
            'name' => $document->contact?->first_name ?: $document->clientName(),
            'number' => $document->number,
            'total' => $document->money($document->total),
            'due_date' => $document instanceof Invoice ? $document->due_date?->isoFormat('LL') : null,
            'valid_until' => $document instanceof Quote ? $document->valid_until?->isoFormat('LL') : null,
            'business_name' => Settings::business()['name'],
        ];
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
