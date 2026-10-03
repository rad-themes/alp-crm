<?php

namespace RadThemes\AlpCrm\Support;

use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Models\Transaction;

/**
 * The "Sales" tab on contact and company profiles.
 */
class Sales
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Contact|Company $record): array
    {
        $document = fn (Quote|Invoice $doc, string $plural) => [
            'id' => $doc->id,
            'number' => $doc->number,
            'title' => $doc->title,
            'total' => $doc->money($doc->total),
            'status' => $doc->displayStatus(),
            'status_label' => Documents::statusLabels()[$doc->displayStatus()] ?? $doc->displayStatus(),
            'date' => $doc->issue_date?->format('Y-m-d'),
            'url' => cp_route("alp-crm.{$plural}.show", $doc),
        ];

        $contactId = $record instanceof Contact ? $record->id : null;

        return [
            'lifetime_value' => Money::format($record->lifetimeValue(), Settings::currency()),
            'outstanding' => Money::format($record->invoices()->outstanding()->get()->sum(fn (Invoice $invoice) => $invoice->balance()), Settings::currency()),
            'quotes' => $record->quotes()->limit(25)->get()->map(fn ($quote) => $document($quote, 'quotes')),
            'invoices' => $record->invoices()->limit(25)->get()->map(fn ($invoice) => $document($invoice, 'invoices')),
            'transactions' => $record->transactions()->limit(25)->get()->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'title' => $transaction->name(),
                'amount' => $transaction->money(),
                'status' => $transaction->status,
                'date' => $transaction->date?->format('Y-m-d'),
                'url' => cp_route('alp-crm.transactions.edit', $transaction),
            ]),
            'create' => [
                'quote' => cp_route('alp-crm.quotes.create', array_filter(['contact' => $contactId])),
                'invoice' => cp_route('alp-crm.invoices.create', array_filter(['contact' => $contactId])),
                'transaction' => cp_route('alp-crm.transactions.create', array_filter(['contact' => $contactId])),
            ],
        ];
    }
}
