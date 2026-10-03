<?php

namespace RadThemes\AlpCrm\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use RadThemes\AlpCrm\Models;

/**
 * Stable names for polymorphic columns (notes, activity, tags, line items),
 * so stored rows don't depend on PHP class names.
 */
class MorphMap
{
    /**
     * @return array<string, class-string>
     */
    public static function map(): array
    {
        return [
            'crm_contact' => Models\Contact::class,
            'crm_company' => Models\Company::class,
            'crm_quote' => Models\Quote::class,
            'crm_invoice' => Models\Invoice::class,
            'crm_transaction' => Models\Transaction::class,
            'crm_task' => Models\Task::class,
        ];
    }

    public static function register(): void
    {
        Relation::morphMap(self::map());
    }
}
