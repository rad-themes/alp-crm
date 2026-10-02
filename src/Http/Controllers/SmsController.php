<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Integrations\Twilio;
use RadThemes\RadpackCrm\Models\Contact;
use Statamic\Http\Controllers\CP\CpController;
use Throwable;

class SmsController extends CpController
{
    public function store(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('edit crm');

        $data = $request->validate(['body' => ['required', 'string', 'max:1600']]);

        try {
            Twilio::send($contact, $data['body']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Text message sent'));
    }
}
