<?php

namespace RadThemes\AlpCrm\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RadThemes\AlpCrm\Models\Contact;
use RadThemes\AlpCrm\Support\Payload;

/**
 * Something happened in the CRM, e.g. "contact.created" or "invoice.paid".
 * Webhooks and automations listen for these; your app can too.
 */
class CrmEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context  extra details, e.g. ['tags' => [...]] or ['from' => 'lead', 'to' => 'customer']
     */
    public function __construct(
        public string $name,
        public array $payload,
        public ?Contact $contact = null,
        public array $context = [],
    ) {}

    /**
     * Every event name, with a label for the Control Panel.
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        return [
            'contact.created' => __('Contact created'),
            'contact.updated' => __('Contact updated'),
            'contact.status_changed' => __('Contact status changed'),
            'contact.tagged' => __('Contact tagged'),
            'contact.unsubscribed' => __('Contact unsubscribed'),
            'contact.deleted' => __('Contact deleted'),
            'company.created' => __('Company created'),
            'company.updated' => __('Company updated'),
            'company.deleted' => __('Company deleted'),
            'form.submitted' => __('Form submitted'),
            'quote.created' => __('Quote created'),
            'quote.sent' => __('Quote sent'),
            'quote.accepted' => __('Quote accepted'),
            'quote.declined' => __('Quote declined'),
            'invoice.created' => __('Invoice created'),
            'invoice.sent' => __('Invoice sent'),
            'invoice.paid' => __('Invoice paid'),
            'transaction.created' => __('Transaction created'),
            'task.created' => __('Task created'),
            'task.completed' => __('Task completed'),
        ];
    }

    public static function fire(string $name, mixed $model, array $context = []): void
    {
        $contact = match (true) {
            $model instanceof Contact => $model,
            is_object($model) && isset($model->contact_id) => $model->contact,
            default => null,
        };

        event(new static($name, Payload::for($model), $contact, $context));
    }
}
