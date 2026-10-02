<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Support\Documents;

/**
 * Client-facing quote and invoice pages, reached from the link in the email.
 */
class PublicDocumentsController
{
    public function invoice(string $token): Response
    {
        return $this->page($this->invoiceFor($token));
    }

    public function invoicePdf(string $token): Response
    {
        return $this->pdf($this->invoiceFor($token));
    }

    public function quote(string $token): Response
    {
        return $this->page($this->quoteFor($token));
    }

    public function quotePdf(string $token): Response
    {
        return $this->pdf($this->quoteFor($token));
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $quote = $this->quoteFor($token);
        $accepted = $request->boolean('accepted');

        if (! $quote->canBeRespondedTo()) {
            return back()->with('radpack_crm_status', __('This quote can no longer be changed.'));
        }

        $quote->respond($accepted, $quote->clientName());

        return back()->with('radpack_crm_status', $accepted
            ? __('Thank you, the quote has been accepted. We will be in touch shortly.')
            : __('Thank you for letting us know.'));
    }

    private function invoiceFor(string $token): Invoice
    {
        $invoice = Invoice::where('token', $token)->first();

        abort_if(! $invoice || $invoice->status === 'draft', 404);

        return $invoice;
    }

    private function quoteFor(string $token): Quote
    {
        $quote = Quote::where('token', $token)->first();

        abort_if(! $quote || $quote->status === 'draft', 404);

        return $quote;
    }

    private function page(Quote|Invoice $document): Response
    {
        return response(Documents::html($document))->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function pdf(Quote|Invoice $document): Response
    {
        return response(Documents::pdf($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Documents::filename($document).'"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
