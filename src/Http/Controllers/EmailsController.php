<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RadThemes\RadpackCrm\Email\EmailSender;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Email;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Write to a contact from their profile — now or at a scheduled time.
 */
class EmailsController extends CpController
{
    public function store(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('edit crm');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'send_at' => ['nullable', 'date', 'after:now'],
        ]);

        abort_unless($contact->email, 422, __('This contact has no email address.'));

        $email = EmailSender::compose($contact, $data['subject'], $data['body'], isset($data['send_at']) ? Carbon::parse($data['send_at']) : null);

        return back()->with($email->status === 'failed' ? 'error' : 'success', match ($email->status) {
            'scheduled' => __('Email scheduled'),
            'failed' => __('The email could not be sent: :error', ['error' => $email->error]),
            default => __('Email sent'),
        });
    }

    public function cancel(Email $email): RedirectResponse
    {
        $this->authorize('edit crm');

        abort_unless($email->status === 'scheduled', 422);

        $email->update(['status' => 'cancelled']);

        return back();
    }
}
