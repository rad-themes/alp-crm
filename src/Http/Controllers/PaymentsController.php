<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Payments\Payments;
use RadThemes\RadpackCrm\Payments\PayPal;
use RadThemes\RadpackCrm\Payments\Stripe;
use RadThemes\RadpackCrm\Support\Documents;
use Throwable;

/**
 * Client-facing "Pay now" flow on public invoice pages, and the Stripe webhook.
 */
class PaymentsController
{
    public function pay(string $token, string $gateway): RedirectResponse
    {
        $invoice = $this->invoice($token);

        if (! $invoice->isPayable() || $invoice->balance() <= 0 || ! isset(Payments::gateways()[$gateway])) {
            return redirect()->to(Documents::publicUrl($invoice))->with('radpack_crm_status', __('This invoice can’t be paid online.'));
        }

        try {
            return redirect()->away($gateway === 'stripe' ? Stripe::checkoutUrl($invoice) : PayPal::checkoutUrl($invoice));
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(Documents::publicUrl($invoice))->with('radpack_crm_status', __('Online payment isn’t available right now. Please try again later.'));
        }
    }

    public function paid(Request $request, string $token, string $gateway): RedirectResponse
    {
        $invoice = $this->invoice($token);
        $paid = false;

        try {
            if ($gateway === 'stripe' && Stripe::configured() && $request->filled('session_id')) {
                $paid = Stripe::completeSession((string) $request->query('session_id'))?->is($invoice) ?? false;
            } elseif ($gateway === 'paypal' && PayPal::configured() && $request->filled('token')) {
                $paid = PayPal::capture($invoice, (string) $request->query('token'));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->to(Documents::publicUrl($invoice))->with('radpack_crm_status', $paid
            ? __('Thank you, your payment has been received.')
            : __('We couldn’t confirm your payment yet. If you completed it, it will appear here shortly.'));
    }

    public function stripeWebhook(Request $request): Response
    {
        $event = Stripe::verifyWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));

        abort_unless($event, 400);

        Stripe::handleWebhook($event);

        return response('ok');
    }

    private function invoice(string $token): Invoice
    {
        $invoice = Invoice::where('token', $token)->first();

        abort_if(! $invoice || $invoice->status === 'draft', 404);

        return $invoice;
    }
}
