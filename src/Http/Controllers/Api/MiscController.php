<?php

namespace RadThemes\AlpCrm\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RadThemes\AlpCrm\Events\CrmEvent;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Note;
use RadThemes\AlpCrm\Models\Webhook;
use RadThemes\AlpCrm\Support\Payload;

class MiscController
{
    /**
     * Connection test (Zapier's "test authentication").
     */
    public function me(Request $request): JsonResponse
    {
        $key = $request->attributes->get('alp_api_key');

        return response()->json(['data' => ['name' => $key->name, 'can_write' => $key->can_write, 'site' => config('app.name')]]);
    }

    public function events(): JsonResponse
    {
        return response()->json(['data' => collect(CrmEvent::names())->map(fn ($label, $name) => ['event' => $name, 'label' => $label])->values()]);
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'type' => ['nullable', Rule::in(Note::TYPES)],
        ]);

        $note = $contact->notes()->create(['type' => $data['type'] ?? 'note', 'body' => $data['body']]);

        return response()->json(['data' => Payload::note($note)], 201);
    }

    /**
     * REST hook subscribe (Zapier, Make, n8n…): {"url": "...", "event": "contact.created"}.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:https,http', 'max:2000'],
            'event' => ['required', Rule::in(array_merge(['*'], array_keys(CrmEvent::names())))],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $webhook = Webhook::create([
            'name' => $data['name'] ?? $request->attributes->get('alp_api_key')->name.' · '.$data['event'],
            'url' => $data['url'],
            'events' => [$data['event']],
            'source' => 'api',
        ]);

        return response()->json(['data' => ['id' => $webhook->id, 'url' => $webhook->url, 'event' => $data['event']]], 201);
    }

    public function unsubscribe(int $id): JsonResponse
    {
        Webhook::where('source', 'api')->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
