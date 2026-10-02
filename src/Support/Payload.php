<?php

namespace RadThemes\RadpackCrm\Support;

use RadThemes\RadpackCrm\Models\Company;
use RadThemes\RadpackCrm\Models\Contact;
use RadThemes\RadpackCrm\Models\Invoice;
use RadThemes\RadpackCrm\Models\LineItem;
use RadThemes\RadpackCrm\Models\Note;
use RadThemes\RadpackCrm\Models\Quote;
use RadThemes\RadpackCrm\Models\Task;
use RadThemes\RadpackCrm\Models\Transaction;

/**
 * The public JSON shape of CRM records, used by the REST API and webhooks.
 */
class Payload
{
    /**
     * @return array<string, mixed>
     */
    public static function for(mixed $model): array
    {
        return match (true) {
            $model instanceof Contact => self::contact($model),
            $model instanceof Company => self::company($model),
            $model instanceof Quote, $model instanceof Invoice => self::document($model),
            $model instanceof Transaction => self::transaction($model),
            $model instanceof Task => self::task($model),
            $model instanceof Note => self::note($model),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function contact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'object' => 'contact',
            'status' => $contact->status,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'name' => $contact->name(),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'company' => $contact->company_id ? ['id' => $contact->company_id, 'name' => $contact->company?->name] : null,
            'owner_id' => $contact->owner_id,
            'user_id' => $contact->user_id,
            'tags' => $contact->exists ? $contact->tags()->pluck('name')->all() : [],
            'fields' => (object) ($contact->data ?? []),
            'subscribed' => $contact->unsubscribed_at === null,
            'last_contacted_at' => $contact->last_contacted_at?->toIso8601String(),
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function company(Company $company): array
    {
        return [
            'id' => $company->id,
            'object' => 'company',
            'name' => $company->name,
            'status' => $company->status,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'owner_id' => $company->owner_id,
            'tags' => $company->exists ? $company->tags()->pluck('name')->all() : [],
            'fields' => (object) ($company->data ?? []),
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(Quote|Invoice $document): array
    {
        $isInvoice = $document instanceof Invoice;

        return [
            'id' => $document->id,
            'object' => $isInvoice ? 'invoice' : 'quote',
            'number' => $document->number,
            'title' => $document->title,
            'status' => $document->displayStatus(),
            'contact_id' => $document->contact_id,
            'company_id' => $document->company_id,
            'currency' => $document->currency,
            'issue_date' => $document->issue_date?->format('Y-m-d'),
            $isInvoice ? 'due_date' : 'valid_until' => ($isInvoice ? $document->due_date : $document->valid_until)?->format('Y-m-d'),
            'subtotal' => (float) $document->subtotal,
            'discount' => (float) $document->discount,
            'tax_total' => (float) $document->tax_total,
            'total' => (float) $document->total,
            'amount_paid' => $isInvoice ? (float) $document->amount_paid : null,
            'balance' => $isInvoice ? $document->balance() : null,
            'items' => $document->items->map(fn (LineItem $item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_name' => $item->tax_name,
                'tax_rate' => (float) $item->tax_rate,
                'total' => (float) $item->total,
            ])->all(),
            'url' => Documents::publicUrl($document),
            'sent_at' => $document->sent_at?->toIso8601String(),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'object' => 'transaction',
            'reference' => $transaction->reference,
            'title' => $transaction->title,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'amount' => (float) $transaction->amount,
            'fee' => (float) $transaction->fee,
            'currency' => $transaction->currency,
            'date' => $transaction->date?->format('Y-m-d'),
            'contact_id' => $transaction->contact_id,
            'company_id' => $transaction->company_id,
            'invoice_id' => $transaction->invoice_id,
            'source' => $transaction->source,
            'external_id' => $transaction->external_id,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function task(Task $task): array
    {
        return [
            'id' => $task->id,
            'object' => 'task',
            'title' => $task->title,
            'type' => $task->type,
            'priority' => $task->priority,
            'description' => $task->description,
            'starts_at' => $task->starts_at?->toIso8601String(),
            'ends_at' => $task->ends_at?->toIso8601String(),
            'all_day' => (bool) $task->all_day,
            'completed' => $task->isDone(),
            'contact_id' => $task->contact_id,
            'company_id' => $task->company_id,
            'assigned_to' => $task->assigned_to,
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function note(Note $note): array
    {
        return [
            'id' => $note->id,
            'object' => 'note',
            'type' => $note->type,
            'body' => $note->body,
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }
}
