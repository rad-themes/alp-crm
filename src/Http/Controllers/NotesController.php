<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Note;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class NotesController extends CpController
{
    private const NOTABLE = ['contact' => Contact::class, 'company' => Company::class];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $notable = (self::NOTABLE[$type] ?? abort(404))::findOrFail($id);

        $data = $request->validate([
            'type' => ['required', Rule::in(Note::TYPES)],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $note = $notable->notes()->create($data + ['user_id' => User::current()->id()]);
        $notable->logActivity('note_added', __(':type logged', ['type' => __(ucfirst($note->type))]), ['note_id' => $note->id]);

        return back();
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('edit crm');

        $note->delete();

        return back();
    }
}
