<?php

namespace RadThemes\RadpackCrm\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\LineItem;
use RadThemes\RadpackCrm\Models\Quote;

/**
 * Presentation and rendering shared by quotes and invoices.
 */
class Documents
{
    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => __('Draft'),
            'sent' => __('Sent'),
            'accepted' => __('Accepted'),
            'declined' => __('Declined'),
            'expired' => __('Expired'),
            'partial' => __('Partly paid'),
            'paid' => __('Paid'),
            'overdue' => __('Overdue'),
            'void' => __('Void'),
        ];
    }

    public static function type(Quote|Invoice $document): string
    {
        return $document instanceof Invoice ? 'invoice' : 'quote';
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Quote|Invoice $document): array
    {
        $status = $document->displayStatus();

        return [
            'id' => $document->id,
            'type' => self::type($document),
            'number' => $document->number,
            'title' => $document->title,
            'status' => $status,
            'status_label' => self::statusLabels()[$status] ?? ucfirst($status),
            'currency' => $document->currency,
            'issue_date' => $document->issue_date?->format('Y-m-d'),
            'second_date' => ($document instanceof Invoice ? $document->due_date : $document->valid_until)?->format('Y-m-d'),
            'client' => $document->clientName(),
            'client_email' => $document->clientEmail(),
            'contact' => $document->contact ? ['id' => $document->contact->id, 'name' => $document->contact->name(), 'url' => cp_route('radpack-crm.contacts.show', $document->contact)] : null,
            'company' => $document->company ? ['id' => $document->company->id, 'name' => $document->company->name, 'url' => cp_route('radpack-crm.companies.show', $document->company)] : null,
            'items' => $document->items->map(fn (LineItem $item) => $item->toEditorArray() + [
                'total' => $item->total,
                'total_formatted' => $document->money($item->total),
                'unit_price_formatted' => $document->money($item->unit_price),
            ])->all(),
            'subtotal' => $document->money($document->subtotal),
            'discount' => (float) $document->discount,
            'discount_formatted' => $document->money($document->discount),
            'tax_total' => $document->money($document->tax_total),
            'total' => $document->money($document->total),
            'total_raw' => (float) $document->total,
            'amount_paid' => $document instanceof Invoice ? $document->money($document->amount_paid) : null,
            'balance' => $document instanceof Invoice ? $document->money($document->balance()) : null,
            'balance_raw' => $document instanceof Invoice ? $document->balance() : null,
            'notes' => $document->notes,
            'terms' => $document->terms,
            'sent_at' => $document->sent_at?->toIso8601String(),
            'public_url' => self::publicUrl($document),
        ];
    }

    public static function publicUrl(Quote|Invoice $document): string
    {
        return route('statamic.radpack-crm.public.'.self::type($document), $document->token);
    }

    public static function html(Quote|Invoice $document, bool $forPdf = false): string
    {
        $document->loadMissing(['items', 'contact', 'company']);

        return view('radpack-crm::documents.show', [
            'document' => $document,
            'type' => self::type($document),
            'business' => Settings::business(),
            'labels' => self::statusLabels(),
            'forPdf' => $forPdf,
        ])->render();
    }

    public static function pdf(Quote|Invoice $document): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $pdf = new Dompdf($options);
        $pdf->loadHtml(self::html($document, forPdf: true));
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public static function filename(Quote|Invoice $document): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $document->number).'.pdf';
    }
}
