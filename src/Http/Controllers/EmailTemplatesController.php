<?php

namespace RadThemes\RadpackCrm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RadThemes\RadpackCrm\Models\EmailTemplate;
use Statamic\CP\PublishForm;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class EmailTemplatesController extends CpController
{
    public function index(): Response
    {
        $this->authorize('view crm');

        return Inertia::render('radpack-crm::Email/Templates', [
            'templates' => EmailTemplate::orderBy('name')->get()->map(fn (EmailTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'updated_at' => $template->updated_at?->toIso8601String(),
                'edit_url' => cp_route('radpack-crm.email-templates.edit', $template),
                'destroy_url' => cp_route('radpack-crm.email-templates.destroy', $template),
            ]),
            'createUrl' => cp_route('radpack-crm.email-templates.create'),
            'canEdit' => User::current()->can('edit crm'),
        ]);
    }

    public function create(): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(EmailTemplate::blueprint())
            ->icon('mail-chat-bubble-text')
            ->title(__('Create Email Template'))
            ->values(['body' => "Hi {{ first_name }},\n\n\n\nThanks,\n{{ business_name }}"])
            ->submittingTo(cp_route('radpack-crm.email-templates.store'), 'POST');
    }

    /**
     * @return array{redirect: string}
     */
    public function store(Request $request): array
    {
        $this->authorize('edit crm');

        (new EmailTemplate)->fillFromBlueprint(PublishForm::make(EmailTemplate::blueprint())->submit($request->all()))->save();

        return ['redirect' => cp_route('radpack-crm.email-templates.index')];
    }

    public function edit(EmailTemplate $emailTemplate): PublishForm
    {
        $this->authorize('edit crm');

        return PublishForm::make(EmailTemplate::blueprint())
            ->icon('mail-chat-bubble-text')
            ->title($emailTemplate->name)
            ->values($emailTemplate->blueprintValues())
            ->submittingTo(cp_route('radpack-crm.email-templates.update', $emailTemplate));
    }

    /**
     * @return array{redirect: string}
     */
    public function update(Request $request, EmailTemplate $emailTemplate): array
    {
        $this->authorize('edit crm');

        $emailTemplate->fillFromBlueprint(PublishForm::make(EmailTemplate::blueprint())->submit($request->all()))->save();

        return ['redirect' => cp_route('radpack-crm.email-templates.index')];
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('delete crm');

        $emailTemplate->delete();

        return back();
    }
}
