<?php

namespace RadThemes\AlpCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Segment;
use RadThemes\AlpCrm\Scopes\CrmSegment;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class SegmentsController extends CpController
{
    public function index(): Response
    {
        $this->authorize('view crm');

        return Inertia::render('alp-crm::Segments/Index', [
            'segments' => Segment::orderBy('name')->get()->map(fn (Segment $segment) => [
                'id' => $segment->id,
                'name' => $segment->name,
                'rules' => count((array) $segment->conditions),
                'contacts' => $segment->contacts()->count(),
                'url' => CrmSegment::url($segment),
                'export_url' => cp_route('alp-crm.export', ['type' => 'contacts', 'segment' => $segment->id]),
                'tag_url' => cp_route('alp-crm.segments.tag', $segment),
                'edit_url' => cp_route('alp-crm.segments.edit', $segment),
                'destroy_url' => cp_route('alp-crm.segments.destroy', $segment),
                'campaign_url' => cp_route('alp-crm.campaigns.create', ['segment' => $segment->id]),
            ]),
            'createUrl' => cp_route('alp-crm.segments.create'),
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('edit crm');

        return $this->editor(new Segment(['match' => 'all', 'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'customer']]]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('edit crm');

        Segment::create($this->validated($request));

        return redirect()->to(cp_route('alp-crm.segments.index'));
    }

    public function edit(Segment $segment): Response
    {
        $this->authorize('edit crm');

        return $this->editor($segment);
    }

    public function update(Request $request, Segment $segment): RedirectResponse
    {
        $this->authorize('edit crm');

        $segment->update($this->validated($request));

        return redirect()->to(cp_route('alp-crm.segments.index'));
    }

    public function destroy(Segment $segment): RedirectResponse
    {
        $this->authorize('delete crm');

        $segment->delete();

        return back();
    }

    /**
     * Bulk tagger: add tags to everyone currently in the segment.
     */
    public function tag(Request $request, Segment $segment): RedirectResponse
    {
        $this->authorize('edit crm');

        $tags = $request->validate(['tags' => ['required', 'array', 'min:1'], 'tags.*' => ['string', 'max:100']])['tags'];

        $count = 0;
        $segment->contacts()->chunkById(200, function ($contacts) use ($tags, &$count) {
            foreach ($contacts as $contact) {
                $contact->attachTags($tags);
                $count++;
            }
        });

        return back()->with('success', trans_choice('Tagged :count contact|Tagged :count contacts', $count));
    }

    /**
     * Live count for the editor.
     *
     * @return array{count: int, sample: array<int, string>}
     */
    public function preview(Request $request): array
    {
        $this->authorize('view crm');

        $query = Segment::applyTo(Contact::query(), (array) $request->input('conditions', []), $request->input('match') === 'any' ? 'any' : 'all');

        return [
            'count' => (clone $query)->count(),
            'sample' => $query->limit(5)->get()->map->name()->all(),
        ];
    }

    private function editor(Segment $segment): Response
    {
        return Inertia::render('alp-crm::Segments/Edit', Segment::editorOptions() + [
            'title' => $segment->exists ? $segment->name : __('Create Segment'),
            'values' => ['name' => $segment->name, 'match' => $segment->match, 'conditions' => array_values((array) $segment->conditions)],
            'previewUrl' => cp_route('alp-crm.segments.preview'),
            'submitUrl' => $segment->exists ? cp_route('alp-crm.segments.update', $segment) : cp_route('alp-crm.segments.store'),
            'submitMethod' => $segment->exists ? 'patch' : 'post',
            'cancelUrl' => cp_route('alp-crm.segments.index'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'match' => ['required', Rule::in(['all', 'any'])],
            'conditions' => ['array', 'max:25'],
            'conditions.*.field' => ['required', Rule::in(array_keys(Segment::fields()))],
            'conditions.*.operator' => ['required', 'string', 'max:30'],
            'conditions.*.value' => ['nullable'],
            'conditions.*.key' => ['nullable', 'regex:/^[A-Za-z0-9_]+$/'],
        ]);
    }
}
