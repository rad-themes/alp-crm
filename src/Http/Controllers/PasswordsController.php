<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Password;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Client password manager. Every reveal is logged on the contact or company.
 */
class PasswordsController extends CpController
{
    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $this->authorize('manage crm passwords');

        $owner = ($type === 'company' ? Company::class : Contact::class)::findOrFail($id);

        Password::create($this->validated($request) + [
            $owner instanceof Contact ? 'contact_id' : 'company_id' => $owner->id,
            'user_id' => User::current()->id(),
        ]);

        return back()->with('success', __('Password saved'));
    }

    public function update(Request $request, Password $password): RedirectResponse
    {
        $this->authorize('manage crm passwords');

        $data = $this->validated($request);

        // An empty password field means "keep the current one".
        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $password->update($data);

        return back()->with('success', __('Password saved'));
    }

    /**
     * @return array{username: ?string, password: ?string, notes: ?string}
     */
    public function reveal(Password $password): array
    {
        $this->authorize('manage crm passwords');

        ($password->contact ?? $password->company)?->logActivity('password_viewed', __('Viewed the password “:label”', ['label' => $password->label]));

        return ['username' => $password->username, 'password' => $password->password, 'notes' => $password->notes];
    }

    public function destroy(Password $password): RedirectResponse
    {
        $this->authorize('manage crm passwords');

        $password->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2000'],
            'username' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
