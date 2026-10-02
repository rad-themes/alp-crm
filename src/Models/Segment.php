<?php

namespace RadThemes\RadpackCrm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A dynamic group of contacts defined by rules, e.g. "customers tagged VIP not contacted in 90 days".
 *
 * @property int $id
 * @property string $name
 * @property string $match all|any
 * @property array<int, array{field: string, operator: string, value?: mixed}> $conditions
 */
class Segment extends Model
{
    protected $table = 'crm_segments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['conditions' => 'array'];
    }

    /**
     * The fields a rule can use and the operators each supports.
     *
     * @return array<string, array{label: string, operators: array<string, string>, input: string}>
     */
    public static function fields(): array
    {
        return [
            'status' => ['label' => __('Status'), 'input' => 'status', 'operators' => ['is' => __('is'), 'is_not' => __('is not')]],
            'tag' => ['label' => __('Tag'), 'input' => 'tag', 'operators' => ['has' => __('has'), 'has_not' => __('doesn’t have')]],
            'company' => ['label' => __('Company'), 'input' => 'none', 'operators' => ['set' => __('is set'), 'empty' => __('is empty')]],
            'email' => ['label' => __('Email'), 'input' => 'text', 'operators' => ['contains' => __('contains'), 'ends_with' => __('ends with'), 'empty' => __('is empty')]],
            'created_at' => ['label' => __('Added'), 'input' => 'days', 'operators' => ['within_days' => __('in the last … days'), 'older_than_days' => __('more than … days ago')]],
            'last_contacted_at' => ['label' => __('Last contacted'), 'input' => 'days', 'operators' => ['within_days' => __('in the last … days'), 'older_than_days' => __('more than … days ago'), 'never' => __('never')]],
            'lifetime_value' => ['label' => __('Lifetime value'), 'input' => 'number', 'operators' => ['gte' => __('at least'), 'lt' => __('less than')]],
            'subscribed' => ['label' => __('Email subscription'), 'input' => 'none', 'operators' => ['yes' => __('subscribed'), 'no' => __('unsubscribed')]],
            'field' => ['label' => __('Custom field'), 'input' => 'field', 'operators' => ['equals' => __('equals'), 'contains' => __('contains'), 'set' => __('is set'), 'empty' => __('is empty')]],
        ];
    }

    /**
     * Options the rule builder needs.
     *
     * @return array{fields: mixed, statuses: mixed, tags: mixed}
     */
    public static function editorOptions(): array
    {
        return [
            'fields' => collect(static::fields())->map(fn ($field, $key) => ['value' => $key] + $field)->values(),
            'statuses' => collect(Contact::blueprint()->field('status')?->get('options') ?? [])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'tags' => Tag::orderBy('name')->get()->map(fn (Tag $tag) => ['value' => $tag->slug, 'label' => $tag->name]),
        ];
    }

    public function contacts(): Builder
    {
        return static::applyTo(Contact::query(), (array) $this->conditions, $this->match);
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     */
    public static function applyTo(Builder $query, array $conditions, string $match = 'all'): Builder
    {
        $conditions = array_values(array_filter($conditions, fn ($condition) => isset(static::fields()[$condition['field'] ?? ''])));

        if (! $conditions) {
            return $query;
        }

        return $query->where(function (Builder $group) use ($conditions, $match) {
            foreach ($conditions as $condition) {
                $method = $match === 'any' ? 'orWhere' : 'where';
                $group->{$method}(fn (Builder $q) => static::applyCondition($q, $condition));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    protected static function applyCondition(Builder $query, array $condition): void
    {
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? null;
        $days = max(0, (int) $value);

        match ($condition['field']) {
            'status' => $operator === 'is_not' ? $query->where('status', '!=', $value) : $query->where('status', $value),
            'tag' => $query->{$operator === 'has_not' ? 'whereDoesntHave' : 'whereHas'}('tags', fn (Builder $tags) => $tags->where('slug', Str::slug((string) $value))),
            'company' => $operator === 'empty' ? $query->whereNull('company_id') : $query->whereNotNull('company_id'),
            'email' => match ($operator) {
                'empty' => $query->where(fn (Builder $q) => $q->whereNull('email')->orWhere('email', '')),
                'ends_with' => $query->where('email', 'like', '%'.static::escapeLike((string) $value)),
                default => $query->where('email', 'like', '%'.static::escapeLike((string) $value).'%'),
            },
            'created_at' => $operator === 'older_than_days'
                ? $query->where('created_at', '<', now()->subDays($days))
                : $query->where('created_at', '>=', now()->subDays($days)),
            'last_contacted_at' => match ($operator) {
                'never' => $query->whereNull('last_contacted_at'),
                'older_than_days' => $query->where(fn (Builder $q) => $q->whereNull('last_contacted_at')->orWhere('last_contacted_at', '<', now()->subDays($days))),
                default => $query->where('last_contacted_at', '>=', now()->subDays($days)),
            },
            // The threshold is inlined (it's a float): SQLite compares a number with a bound string as "less than".
            'lifetime_value' => $query->whereRaw(
                "coalesce((select sum(case when crm_transactions.type = 'refund' then -crm_transactions.amount else crm_transactions.amount end) from crm_transactions where crm_transactions.contact_id = crm_contacts.id and crm_transactions.status = 'succeeded'), 0) "
                .($operator === 'lt' ? '<' : '>=').' '.(float) $value,
            ),
            'subscribed' => $operator === 'no' ? $query->whereNotNull('unsubscribed_at') : $query->whereNull('unsubscribed_at'),
            'field' => static::applyFieldCondition($query, (string) ($condition['key'] ?? ''), $operator, $value),
        };
    }

    protected static function applyFieldCondition(Builder $query, string $key, string $operator, mixed $value): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $key)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $column = "data->{$key}";

        match ($operator) {
            'set' => $query->whereNotNull($column),
            'empty' => $query->whereNull($column),
            'contains' => $query->where($column, 'like', '%'.static::escapeLike((string) $value).'%'),
            default => $query->where($column, $value),
        };
    }

    protected static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
