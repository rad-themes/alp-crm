<?php

namespace RadThemes\RadpackCrm\Listeners;

use RadThemes\RadpackCrm\Capture\LeadCapture;
use RadThemes\RadpackCrm\Events\CrmEvent;
use RadThemes\RadpackCrm\Support\Settings;
use Statamic\Events\SubmissionCreated;

/**
 * Statamic form submissions → CRM contacts (the "lead capture" setting).
 */
class CaptureFormSubmission
{
    public function handle(SubmissionCreated $event): void
    {
        $submission = $event->submission;
        $form = $submission->form();

        if (! $form || ! in_array($form->handle(), (array) Settings::get('capture_forms', []), true)) {
            return;
        }

        [$attributes, $rest] = LeadCapture::map($submission->data()->all());

        $contact = LeadCapture::upsert(
            $attributes,
            (string) Settings::get('capture_status', 'lead'),
            array_merge((array) Settings::get('capture_tags', []), [$form->title()]),
        );

        if (! $contact) {
            return;
        }

        $contact->logActivity('form_submitted', __('Submitted the “:form” form', ['form' => $form->title()]), ['form' => $form->handle(), 'submission' => $submission->id()]);

        if ($rest && Settings::get('capture_note', true)) {
            $lines = collect($rest)->map(function ($value, $handle) use ($form) {
                $label = $form->blueprint()?->field($handle)?->display() ?? $handle;

                return $label.': '.(is_array($value) ? implode(', ', array_map('strval', array_filter($value, 'is_scalar'))) : $value);
            });

            $contact->notes()->create(['type' => 'note', 'body' => __('Form: :form', ['form' => $form->title()])."\n\n".$lines->implode("\n")]);
        }

        CrmEvent::fire('form.submitted', $contact, ['form' => $form->handle(), 'fields' => $submission->data()->all()]);
    }
}
