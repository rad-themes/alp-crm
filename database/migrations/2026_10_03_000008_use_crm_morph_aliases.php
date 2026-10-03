<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Polymorphic columns stored PHP class names. Switch them to stable aliases
 * (crm_contact, crm_invoice…), including rows written by Radpack CRM, this addon's former name.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'crm_notes' => 'notable_type',
        'crm_activities' => 'subject_type',
        'crm_taggables' => 'taggable_type',
        'crm_line_items' => 'document_type',
    ];

    private const MODELS = [
        'crm_contact' => 'Contact',
        'crm_company' => 'Company',
        'crm_quote' => 'Quote',
        'crm_invoice' => 'Invoice',
        'crm_transaction' => 'Transaction',
        'crm_task' => 'Task',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach (self::MODELS as $alias => $model) {
                DB::table($table)
                    ->whereIn($column, ["RadThemes\\RadpackCrm\\Models\\{$model}", "RadThemes\\AlpCrm\\Models\\{$model}"])
                    ->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach (self::MODELS as $alias => $model) {
                DB::table($table)->where($column, $alias)->update([$column => "RadThemes\\AlpCrm\\Models\\{$model}"]);
            }
        }
    }
};
