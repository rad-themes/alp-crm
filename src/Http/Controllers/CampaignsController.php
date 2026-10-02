<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Email\CampaignMail;
use RadThemes\RadpackCrm\Email\CampaignSender;
use RadThemes\RadpackCrm\Email\MergeTags;
use RadThemes\RadpackCrm\Models\Campaign;
use RadThemes\RadpackCrm\Models\CampaignRecipient;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\EmailTemplate;
use RadThemes\RadpackCrm\Models\Segment;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class CampaignsController extends CpController
{
    public function index(): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Campaigns/Index', [
            'campaigns' => Campaign::with('segment')->latest()->latest('id')->get()->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'segment' => $campaign->segment?->name ?? __('All subscribed contacts'),
                'status' => $campaign->status,
                'stats' => $campaign->status === 'draft' ? null : $campaign->stats(),
                'date' => ($campaign->finished_at ?? $campaign->started_at ?? $campaign->scheduled_at ?? $campaign->updated_at)?->toIso8601String(),
                'url' => cp_route($campaign->isEditable() ? 'radpack-crm.campaigns.edit' : 'radpack-crm.campaigns.show', $campaign),
            ]),
            'createUrl' => cp_route('radpack-crm.campaigns.create'),
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('edit crm');

        return $this->editor(new Campaign([
            'segment_id' => $request->integer('segment') ?: null,
            'body' => "Hi {{ first_name }},\n\n\n\n{{ business_name }}",
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('edit crm');

        $campaign = Campaign::create($this->validated($request) + ['status' => 'draft', 'user_id' => User::current()->id()]);

        return redirect()->to(cp_route('radpack-crm.campaigns.edit', $campaign))->with('success', __('Saved'));
    }

    public function edit(Campaign $campaign): Response|RedirectResponse
    {
        $this->authorize('edit crm');

        if (! $campaign->isEditable()) {
            return redirect()->to(cp_route('radpack-crm.campaigns.show', $campaign));
        }

        return $this->editor($campaign);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('edit crm');
        abort_unless($campaign->isEditable(), 422, __('This campaign has already been sent.'));

        $campaign->update($this->validated($request));

        return back()->with('success', __('Saved'));
    }

    public function show(Campaign $campaign): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Campaigns/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => MergeTags::render($campaign->subject, MergeTags::for(null)),
                'status' => $campaign->status,
                'segment' => $campaign->segment?->name ?? __('All subscribed contacts'),
                'started_at' => $campaign->started_at?->toIso8601String(),
                'finished_at' => $campaign->finished_at?->toIso8601String(),
                'html' => MergeTags::html(MergeTags::render($campaign->body, MergeTags::for(null))),
            ],
            'stats' => $campaign->stats(),
            'recipients' => $campaign->recipients()->with('contact')->orderByDesc('opened_at')->limit(200)->get()->map(fn (CampaignRecipient $recipient) => [
                'id' => $recipient->id,
                'email' => $recipient->email,
                'name' => $recipient->contact?->name(),
                'url' => $recipient->contact ? cp_route('radpack-crm.contacts.show', $recipient->contact) : null,
                'status' => $recipient->status,
                'opened' => $recipient->opened_at !== null,
                'clicks' => $recipient->clicks,
            ]),
            'urls' => ['index' => cp_route('radpack-crm.campaigns.index'), 'cancel' => cp_route('radpack-crm.campaigns.cancel', $campaign)],
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function test(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('edit crm');

        $data = $request->validate(['email' => ['required', 'email']]);

        $recipient = new CampaignRecipient(['email' => $data['email'], 'token' => 'test-'.Str::random(20)]);
        $recipient->setRelation('campaign', $campaign);
        $recipient->setRelation('contact', Contact::findByEmail($data['email']));

        Mail::to($data['email'])->send(new CampaignMail($recipient));

        return back()->with('success', __('Test email sent to :email', ['email' => $data['email']]));
    }

    /**
     * Send now, or schedule for later.
     */
    public function send(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('edit crm');
        abort_unless($campaign->isEditable(), 422, __('This campaign has already been sent.'));

        $data = $request->validate(['scheduled_at' => ['nullable', 'date', 'after:now']]);

        if (! empty($data['scheduled_at'])) {
            $campaign->update(['status' => 'scheduled', 'scheduled_at' => $data['scheduled_at']]);

            return redirect()->to(cp_route('radpack-crm.campaigns.index'))->with('success', __('Campaign scheduled'));
        }

        CampaignSender::start($campaign);
        CampaignSender::sendBatch($campaign->fresh());

        return redirect()->to(cp_route('radpack-crm.campaigns.show', $campaign))->with('success', __('Sending started'));
    }

    public function cancel(Campaign $campaign): RedirectResponse
    {
        $this->authorize('edit crm');

        if (in_array($campaign->status, ['scheduled', 'sending'], true)) {
            $campaign->recipients()->where('status', 'queued')->delete();
            $campaign->update(['status' => $campaign->status === 'scheduled' ? 'draft' : 'cancelled', 'finished_at' => $campaign->status === 'sending' ? now() : null]);
        }

        return back();
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete crm');

        $campaign->delete();

        return redirect()->to(cp_route('radpack-crm.campaigns.index'));
    }

    private function editor(Campaign $campaign): Response
    {
        return Inertia::render('radpack-crm::Campaigns/Edit', [
            'title' => $campaign->exists ? $campaign->name : __('Create Campaign'),
            'values' => [
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'body' => $campaign->body,
                'segment_id' => $campaign->segment_id,
            ],
            'status' => $campaign->status ?? 'draft',
            'scheduledAt' => $campaign->scheduled_at?->toIso8601String(),
            'segments' => Segment::orderBy('name')->get()->map(fn (Segment $segment) => ['value' => $segment->id, 'label' => $segment->name]),
            'audienceCounts' => Segment::all()->mapWithKeys(fn (Segment $segment) => [$segment->id => (new Campaign(['segment_id' => $segment->id]))->audience()->count()])
                ->put('all', (new Campaign)->audience()->count()),
            'templates' => EmailTemplate::orderBy('name')->get(['id', 'name', 'subject', 'body']),
            'mergeTags' => MergeTags::available(),
            'submitUrl' => $campaign->exists ? cp_route('radpack-crm.campaigns.update', $campaign) : cp_route('radpack-crm.campaigns.store'),
            'submitMethod' => $campaign->exists ? 'patch' : 'post',
            'urls' => $campaign->exists ? [
                'send' => cp_route('radpack-crm.campaigns.send', $campaign),
                'test' => cp_route('radpack-crm.campaigns.test', $campaign),
                'cancel' => cp_route('radpack-crm.campaigns.cancel', $campaign),
                'destroy' => cp_route('radpack-crm.campaigns.destroy', $campaign),
            ] : null,
            'indexUrl' => cp_route('radpack-crm.campaigns.index'),
            'userEmail' => User::current()->email(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'segment_id' => ['nullable', Rule::exists('crm_segments', 'id')],
        ]);
    }
}
