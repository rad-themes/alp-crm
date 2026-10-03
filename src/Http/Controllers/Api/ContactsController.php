<?php

namespace RadThemes\AlpCrm\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Support\Payload;

class ContactsController extends RecordsController
{
    protected function model(): string
    {
        return Contact::class;
    }

    protected function filter(Builder $query, Request $request): void
    {
        $query->with('company');

        if ($search = $request->query('search')) {
            $query->search((string) $search);
        }

        if ($email = $request->query('email')) {
            $contact = Contact::findByEmail((string) $email);
            $query->whereKey($contact?->id ?? 0);
        }

        foreach (['status', 'company_id'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->query($column));
            }
        }

        if ($tag = $request->query('tag')) {
            $query->withTag((string) $tag);
        }
    }

    /**
     * Create or update by email: handy for Zapier and form integrations.
     */
    public function upsert(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $contact = Contact::findByEmail($request->input('email'));

        return $contact ? $this->update($request, $contact->id) : $this->store($request);
    }

    public function tag(Request $request, int $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        $tags = $request->validate(['tags' => ['required', 'array'], 'tags.*' => ['string', 'max:100']])['tags'];

        $request->isMethod('delete')
            ? $contact->tags()->detach($contact->tags()->whereIn('name', $tags)->pluck('crm_tags.id'))
            : $contact->attachTags($tags);

        return response()->json(['data' => Payload::contact($contact->fresh())]);
    }
}
