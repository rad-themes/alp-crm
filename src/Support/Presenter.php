<?php

namespace RadThemes\AlpCrm\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RadThemes\AlpCrm\Models\Activity;
use RadThemes\AlpCrm\Models\Company;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Models\Invoice;
use RadThemes\AlpCrm\Models\Note;
use RadThemes\AlpCrm\Models\Quote;
use RadThemes\AlpCrm\Models\Transaction;
use Statamic\Facades\Dictionary;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;

/**
 * Shapes CRM records for the Control Panel's Vue pages.
 */
class Presenter
{
    public static function optionLabel(Blueprint $blueprint, string $handle, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $blueprint->field($handle)?->get('options')[$value] ?? ucfirst($value);
    }

    /**
     * The Control Panel page for a CRM record.
     */
    public static function url(Model $record): ?string
    {
        $route = match (true) {
            $record instanceof Contact => 'alp-crm.contacts.show',
            $record instanceof Company => 'alp-crm.companies.show',
            $record instanceof Invoice => 'alp-crm.invoices.show',
            $record instanceof Quote => 'alp-crm.quotes.show',
            $record instanceof Transaction => 'alp-crm.transactions.edit',
            default => null,
        };

        return $route ? cp_route($route, $record) : null;
    }

    public static function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }

    /**
     * Label/value pairs for every filled blueprint field that isn't shown elsewhere — including custom fields.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $except
     * @return array<int, array{handle: string, label: string, value: string, url: ?string}>
     */
    public static function details(Blueprint $blueprint, array $values, array $except = []): array
    {
        return $blueprint->fields()->all()
            ->reject(fn (Field $field, string $handle) => in_array($handle, $except, true))
            ->map(fn (Field $field, string $handle) => [
                'handle' => $handle,
                'label' => __($field->display()),
                'value' => self::formatValue($field, $values[$handle] ?? null),
                'url' => in_array($field->get('input_type'), ['url'], true) ? self::safeUrl($values[$handle] ?? null) : null,
            ])
            ->filter(fn (array $detail) => $detail['value'] !== '')
            ->values()
            ->all();
    }

    private static function formatValue(Field $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if ($field->type() === 'dictionary' && $field->get('dictionary') === 'countries') {
            return collect($value)->map(fn ($code) => Dictionary::find('countries')->get($code)?->label() ?? $code)->implode(', ');
        }

        if (in_array($field->type(), ['select', 'button_group', 'radio', 'checkboxes'], true)) {
            $options = (array) $field->get('options');

            return collect($value)->map(fn ($v) => $options[$v] ?? $v)->implode(', ');
        }

        if ($field->type() === 'toggle') {
            return $value ? __('Yes') : __('No');
        }

        if (is_array($value)) {
            return collect($value)->flatten()->filter(fn ($v) => is_scalar($v))->implode(', ');
        }

        return (string) $value;
    }

    /**
     * @param  Collection<int, Note>  $notes
     * @return array<int, array<string, mixed>>
     */
    public static function notes(Collection $notes): array
    {
        return $notes->map(fn (Note $note) => [
            'id' => $note->id,
            'type' => $note->type,
            'type_label' => self::noteTypes()[$note->type] ?? ucfirst($note->type),
            'body' => $note->body,
            'author' => $note->author()?->name(),
            'created_at' => $note->created_at?->toIso8601String(),
            'destroy_url' => cp_route('alp-crm.notes.destroy', $note),
        ])->all();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, array<string, mixed>>
     */
    public static function activities(Collection $activities): array
    {
        return $activities->take(50)->map(fn (Activity $activity) => [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'causer' => $activity->causer()?->name(),
            'created_at' => $activity->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    public static function noteTypes(): array
    {
        return [
            'note' => __('Note'),
            'call' => __('Call'),
            'meeting' => __('Meeting'),
            'email' => __('Email'),
            'sms' => __('SMS'),
        ];
    }

    /**
     * Only http(s) links: field values can come from forms, imports or the API,
     * and a "javascript:" link would run in the Control Panel when clicked.
     */
    public static function safeUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', trim($url)) ? trim($url) : null;
    }
}
