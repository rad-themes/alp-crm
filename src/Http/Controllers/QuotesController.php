<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Quote;
use Statamic\Facades\User;

class QuotesController extends DocumentsController
{
    protected function model(): string
    {
        return Quote::class;
    }

    protected function type(): string
    {
        return 'quote';
    }

    protected function plural(): string
    {
        return 'quotes';
    }

    protected function secondDateColumn(): string
    {
        return 'valid_until';
    }

    /**
     * Mark accepted or declined on the client's behalf (e.g. they replied by email).
     */
    public function respond(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $data = $request->validate(['accepted' => ['required', 'boolean']]);

        Quote::findOrFail($id)->respond($data['accepted'], User::current()->name());

        return back();
    }

    public function convert(int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $invoice = Quote::with('items')->findOrFail($id)->convertToInvoice();

        return redirect()->to(cp_route('radpack-crm.invoices.show', $invoice));
    }

    protected function urls(Quote|Invoice $document): array
    {
        return parent::urls($document) + [
            'respond' => cp_route('radpack-crm.quotes.respond', $document),
            'convert' => cp_route('radpack-crm.quotes.convert', $document),
        ];
    }

    protected function extraShowProps(Quote|Invoice $document): array
    {
        return [
            'invoice' => $document->invoice ? ['number' => $document->invoice->number, 'url' => cp_route('radpack-crm.invoices.show', $document->invoice)] : null,
            'responded' => $document->responded_at ? ['at' => $document->responded_at->toIso8601String(), 'by' => $document->responded_by] : null,
        ];
    }
}
