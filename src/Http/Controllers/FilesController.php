<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\File;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends CpController
{
    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $this->authorize('edit crm');

        $owner = ($type === 'company' ? Company::class : Contact::class)::findOrFail($id);
        $data = $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:51200'],
            'portal' => ['boolean'],
        ]);

        foreach ($data['files'] as $upload) {
            $file = File::store($upload, $owner, User::current()->id());
            $file->update(['portal' => $data['portal'] ?? false]);
            $owner->logActivity('file_added', __('Added the file “:name”', ['name' => $file->name]));
        }

        return back()->with('success', trans_choice('Uploaded :count file|Uploaded :count files', count($data['files'])));
    }

    public function download(File $file): StreamedResponse
    {
        $this->authorize('view crm');

        return $file->download();
    }

    public function update(Request $request, File $file): RedirectResponse
    {
        $this->authorize('edit crm');

        $file->update($request->validate(['portal' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(File $file): RedirectResponse
    {
        $this->authorize('delete crm');

        $file->delete();

        return back();
    }
}
