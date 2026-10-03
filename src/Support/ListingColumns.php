<?php

namespace RadThemes\AlpCrm\Support;

use Illuminate\Http\Request;

/**
 * Column definitions for the Control Panel listings. Statamic's Listing component expects
 * them in every JSON response (meta.columns), with the visibility the user chose.
 */
class ListingColumns
{
    /**
     * @return array<int, array{field: string, label: string, sortable: bool, visible: bool}>
     */
    public static function for(string $listing, ?string $type = null): array
    {
        $col = fn (string $field, string $label, bool $sortable = false, bool $visible = true) => compact('field', 'label', 'sortable', 'visible');
        $isInvoice = $type === 'invoice';

        return match ($listing) {
            'contacts' => [
                $col('name', __('Name'), true),
                $col('email', __('Email'), true),
                $col('phone', __('Phone'), false, false),
                $col('company', __('Company')),
                $col('status', __('Status'), true),
                $col('tags', __('Tags')),
                $col('last_contacted_at', __('Last contacted'), true, false),
                $col('created_at', __('Added'), true),
            ],
            'companies' => [
                $col('name', __('Name'), true),
                $col('email', __('Email'), true),
                $col('phone', __('Phone'), false, false),
                $col('website', __('Website'), false, false),
                $col('contacts_count', __('Contacts'), true),
                $col('status', __('Status'), true),
                $col('tags', __('Tags')),
                $col('created_at', __('Added'), true),
            ],
            'documents' => array_values(array_filter([
                $col('number', __('Number'), true),
                $col('client', __('Client')),
                $col('title', __('Title')),
                $col('issue_date', __('Issued'), true),
                $isInvoice ? $col('due_date', __('Due'), true) : $col('valid_until', __('Valid until'), true),
                $col('total', __('Total'), true),
                $isInvoice ? $col('balance', __('Balance')) : null,
                $col('status', __('Status'), true),
            ])),
            'transactions' => [
                $col('date', __('Date'), true),
                $col('title', __('Title'), true),
                $col('contact', __('Contact')),
                $col('invoice', __('Invoice')),
                $col('reference', __('Reference'), false, false),
                $col('source', __('Source'), false, false),
                $col('amount', __('Amount'), true),
                $col('status', __('Status'), true),
            ],
            'tasks' => [
                $col('done', ''),
                $col('title', __('Task'), true),
                $col('starts_at', __('Due'), true),
                $col('contact', __('Contact')),
                $col('assignee', __('Assigned to')),
                $col('priority', __('Priority'), true),
            ],
        };
    }

    /**
     * The columns with the visibility requested by the listing (?columns=a,b,c).
     *
     * @return array<int, array{field: string, label: string, sortable: bool, visible: bool}>
     */
    public static function fromRequest(Request $request, string $listing, ?string $type = null): array
    {
        $columns = self::for($listing, $type);

        if (! $request->filled('columns')) {
            return $columns;
        }

        $visible = explode(',', (string) $request->input('columns'));

        return array_map(fn ($column) => ['visible' => in_array($column['field'], $visible, true)] + $column, $columns);
    }
}
