<?php

namespace RadThemes\RadpackCrm\Email;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Email;
use Statamic\Facades\User;
use Throwable;

/**
 * Sends (or schedules) one-to-one emails to contacts and logs them on the contact.
 */
class EmailSender
{
    public static function compose(Contact $contact, string $subject, string $body, ?\DateTimeInterface $sendAt = null): Email
    {
        $variables = MergeTags::for($contact);

        $email = $contact->emails()->create([
            'to' => $contact->email,
            'subject' => MergeTags::render($subject, $variables),
            'body' => MergeTags::render($body, $variables),
            'status' => $sendAt ? 'scheduled' : 'sending',
            'scheduled_at' => $sendAt,
            'user_id' => User::current()?->id(),
        ]);

        if (! $sendAt) {
            self::deliver($email);
        }

        return $email->fresh();
    }

    public static function deliver(Email $email): void
    {
        try {
            Mail::to($email->to)->send(new ContactMail($email->subject, $email->body));
            $email->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);

            if ($contact = $email->contact) {
                $contact->forceFill(['last_contacted_at' => now()])->saveQuietly();
                $contact->logActivity('email_sent', __('Emailed “:subject”', ['subject' => $email->subject]), ['email_id' => $email->id]);
            }
        } catch (Throwable $e) {
            $email->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500)]);
        }
    }
}
