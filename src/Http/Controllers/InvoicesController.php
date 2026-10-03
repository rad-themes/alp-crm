<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Models\Transaction;

class InvoicesController extends DocumentsController
{
    protected function model(): string
    {
        return Invoice::class;
    }

    protected function type(): string
    {
        return 'invoice';
    }

    protected function plural(): string
    {
        return 'invoices';
    }

    protected function secondDateColumn(): string
    {
        return 'due_date';
    }

    public function recordPayment(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $invoice = Invoice::findOrFail($id);

        abort_if($invoice->status === 'void', 422, __('This invoice is void.'));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', 'max:100'],
        ]);

        $invoice->recordPayment((float) $data['amount'], [
            'date' => $data['date'],
            'reference' => $data['reference'] ?? null,
            'source' => ($data['method'] ?? null) ?: 'manual',
        ]);

        return back()->with('success', __('Payment recorded'));
    }

    public function void(int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => 'void']);

        return back();
    }

    protected function urls(Quote|Invoice $document): array
    {
        return parent::urls($document) + [
            'payment' => cp_route('alp-crm.invoices.payments.store', $document),
            'void' => cp_route('alp-crm.invoices.void', $document),
        ];
    }

    protected function extraShowProps(Quote|Invoice $document): array
    {
        return [
            'payments' => $document->payments->map(fn (Transaction $payment) => [
                'id' => $payment->id,
                'amount' => $payment->money(),
                'date' => $payment->date?->format('Y-m-d'),
                'reference' => $payment->reference,
                'source' => $payment->source,
                'status' => $payment->status,
                'url' => cp_route('alp-crm.transactions.edit', $payment),
            ]),
            'quote' => $document->quote ? ['number' => $document->quote->number, 'url' => cp_route('alp-crm.quotes.show', $document->quote)] : null,
        ];
    }
}
