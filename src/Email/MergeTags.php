<?php

namespace RadThemes\RadpackCrm\Email;

use Illuminate\Support\Str;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Support\Settings;
use Statamic\Facades\Antlers;

/**
 * Personalises templates with Antlers merge tags, e.g. "Hi {{ first_name }}".
 */
class MergeTags
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Contact $contact): array
    {
        $business = Settings::business();

        return array_merge(
            $contact ? array_filter((array) $contact->data, 'is_scalar') : [],
            [
                'first_name' => $contact?->first_name ?: __('there'),
                'last_name' => $contact?->last_name,
                'name' => $contact?->name(),
                'email' => $contact?->email,
                'phone' => $contact?->phone,
                'company' => $contact?->company?->name,
                'business_name' => $business['name'],
                'business_email' => $business['email'],
            ],
        );
    }

    /**
     * @return array<int, array{tag: string, label: string}>
     */
    public static function available(): array
    {
        return [
            ['tag' => '{{ first_name }}', 'label' => __('First name')],
            ['tag' => '{{ last_name }}', 'label' => __('Last name')],
            ['tag' => '{{ name }}', 'label' => __('Full name')],
            ['tag' => '{{ email }}', 'label' => __('Email')],
            ['tag' => '{{ company }}', 'label' => __('Company')],
            ['tag' => '{{ business_name }}', 'label' => __('Your business name')],
        ];
    }

    /**
     * Render merge tags. Templates are untrusted: only variables are available, no tags or PHP.
     *
     * @param  array<string, mixed>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        return trim((string) Antlers::parse($template, $variables, false));
    }

    /**
     * Markdown → HTML for emails. Raw HTML in the body is stripped and unsafe links are dropped.
     */
    public static function html(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
