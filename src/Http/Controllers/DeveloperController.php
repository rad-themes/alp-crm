<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Jobs\DeliverWebhook;
use RadThemes\RadpackCrm\Models\ApiKey;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Webhook;
use RadThemes\RadpackCrm\Support\Payload;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * API keys and webhooks. Managing them needs "configure addons" (they grant access to all CRM data).
 */
class DeveloperController extends CpController
{
    public function index(): Response
    {
        $this->authorize('configure addons');

        return Inertia::render('radpack-crm::Developer', [
            'apiUrl' => url('api/radpack-crm/v1'),
            'keys' => ApiKey::latest('id')->get()->map(fn (ApiKey $key) => [
                'id' => $key->id,
                'name' => $key->name,
                'hint' => $key->hint,
                'can_write' => $key->can_write,
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
                'destroy_url' => cp_route('radpack-crm.developer.keys.destroy', $key),
            ]),
            'webhooks' => Webhook::latest('id')->get()->map(fn (Webhook $webhook) => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'active' => $webhook->active,
                'source' => $webhook->source,
                'secret' => $webhook->secret,
                'last_status' => $webhook->last_status,
                'last_error' => $webhook->last_error,
                'last_sent_at' => $webhook->last_sent_at?->toIso8601String(),
                'update_url' => cp_route('radpack-crm.developer.webhooks.update', $webhook),
                'destroy_url' => cp_route('radpack-crm.developer.webhooks.destroy', $webhook),
                'test_url' => cp_route('radpack-crm.developer.webhooks.test', $webhook),
            ]),
            'events' => collect(CrmEvent::names())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'urls' => [
                'storeKey' => cp_route('radpack-crm.developer.keys.store'),
                'storeWebhook' => cp_route('radpack-crm.developer.webhooks.store'),
            ],
            'newKey' => session('radpack_new_api_key'),
        ]);
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $this->authorize('configure addons');

        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'can_write' => ['boolean']]);

        [, $plain] = ApiKey::generate($data['name'], $data['can_write'] ?? true, User::current()->id());

        return back()->with('radpack_new_api_key', $plain);
    }

    public function destroyKey(ApiKey $key): RedirectResponse
    {
        $this->authorize('configure addons');

        $key->delete();

        return back()->with('success', __('API key revoked'));
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $this->authorize('configure addons');

        Webhook::create($this->validated($request) + ['source' => 'cp']);

        return back()->with('success', __('Webhook added'));
    }

    public function updateWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure addons');

        $webhook->update($this->validated($request));

        return back()->with('success', __('Saved'));
    }

    public function destroyWebhook(Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure addons');

        $webhook->delete();

        return back();
    }

    /**
     * Send a sample "contact.created" event right away.
     */
    public function testWebhook(Webhook $webhook): RedirectResponse
    {
        $this->authorize('configure addons');

        $sample = Contact::latest('id')->first() ?? new Contact(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'status' => 'lead']);

        DeliverWebhook::dispatchSync($webhook->id, [
            'id' => (string) Str::uuid(),
            'event' => 'contact.created',
            'created_at' => now()->toIso8601String(),
            'data' => Payload::contact($sample),
            'context' => (object) ['test' => true],
        ]);

        $webhook->refresh();

        return back()->with(
            $webhook->last_status && $webhook->last_status < 300 ? 'success' : 'error',
            $webhook->last_status ? __('The endpoint answered :status', ['status' => $webhook->last_status]) : __('Delivery failed: :error', ['error' => $webhook->last_error]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:https,http', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(array_merge(['*'], array_keys(CrmEvent::names())))],
            'active' => ['boolean'],
        ]);
    }
}
