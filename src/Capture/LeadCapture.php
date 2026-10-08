<?php

namespace RadThemes\AlpCrm\Capture;

use Illuminate\Support\Str;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;

/**
 * Turns submitted data (a form, a registration, an import row…) into a contact,
 * creating one or filling the gaps on an existing contact with the same email.
 */
class LeadCapture
{
    /**
     * Common field handles and the contact field they mean.
     */
    private const ALIASES = [
        'email' => ['email', 'email_address', 'your_email', 'e_mail', 'mail'],
        'first_name' => ['first_name', 'firstname', 'fname', 'given_name'],
        'last_name' => ['last_name', 'lastname', 'lname', 'surname', 'family_name'],
        'name' => ['name', 'full_name', 'fullname', 'your_name'],
        'phone' => ['phone', 'telephone', 'tel', 'mobile', 'phone_number', 'cell'],
        'company' => ['company', 'company_name', 'organisation', 'organization', 'business', 'business_name'],
    ];

    /**
     * Split submitted data into contact attributes and leftovers (for the note).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function map(array $data): array
    {
        $aliases = collect(self::ALIASES)->flatMap(fn ($handles, $target) => array_fill_keys($handles, $target));
        $custom = collect(Contact::blueprint()->fields()->all())->keys()
            ->diff(['first_name', 'last_name', 'email', 'phone', 'company', 'owner', 'portal_user', 'tags', 'aliases', 'status'])
            ->all();

        $mapped = [];
        $rest = [];

        foreach ($data as $handle => $value) {
            $key = Str::snake(mb_strtolower((string) $handle));

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($target = $aliases[$key] ?? null) {
                $mapped[$target] ??= is_array($value) ? implode(', ', $value) : trim((string) $value);
            } elseif (in_array($key, $custom, true) && is_scalar($value)) {
                $mapped['fields'][$key] = $value;
            } else {
                $rest[$handle] = $value;
            }
        }

        if (isset($mapped['name']) && ! isset($mapped['first_name'])) {
            [$mapped['first_name'], $mapped['last_name']] = array_pad(explode(' ', $mapped['name'], 2), 2, null);
        }
        unset($mapped['name']);

        return [$mapped, $rest];
    }

    /**
     * @param  array<string, mixed>  $attributes  mapped attributes (see map())
     * @param  array<int, string>  $tags
     */
    public static function upsert(array $attributes, string $status = 'lead', array $tags = []): ?Contact
    {
        $email = isset($attributes['email']) ? mb_strtolower($attributes['email']) : null;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $contact = Contact::findByEmail($email) ?? new Contact(['email' => $email, 'status' => $status ?: 'lead']);

        foreach (['first_name', 'last_name', 'phone'] as $column) {
            if (! $contact->{$column} && ! empty($attributes[$column])) {
                $contact->{$column} = Str::limit($attributes[$column], 250, '');
            }
        }

        if (! $contact->company_id && ! empty($attributes['company'])) {
            $contact->company_id = Company::firstOrCreate(['name' => Str::limit($attributes['company'], 250, '')])->id;
        }

        if (! empty($attributes['fields'])) {
            $contact->data = array_merge($attributes['fields'], array_filter((array) $contact->data, fn ($value) => $value !== null && $value !== ''));
        }

        $contact->save();

        if ($tags) {
            $contact->attachTags($tags);
        }

        return $contact;
    }
}
