<?php

namespace RadThemes\RadpackCrm\Automations;

/**
 * Ready-made automations to start from.
 */
class Recipes
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'welcome' => [
                'label' => __('Welcome new leads'),
                'description' => __('Tag new contacts from forms and email them a welcome a day later.'),
                'icon' => 'mail-chat-bubble-text',
                'values' => [
                    'name' => __('Welcome new leads'),
                    'trigger' => 'form.submitted',
                    'actions' => [
                        ['type' => 'add_tag', 'tags' => 'New lead', 'delay' => 0, 'delay_unit' => 'minutes'],
                        ['type' => 'send_email', 'template_id' => null, 'delay' => 1, 'delay_unit' => 'days'],
                    ],
                ],
            ],
            'quote_accepted' => [
                'label' => __('Accepted quote → customer'),
                'description' => __('Make the contact a customer and create a kick-off task for the owner.'),
                'icon' => 'file-content-list',
                'values' => [
                    'name' => __('Accepted quote → customer'),
                    'trigger' => 'quote.accepted',
                    'actions' => [
                        ['type' => 'set_status', 'status' => 'customer', 'delay' => 0, 'delay_unit' => 'minutes'],
                        ['type' => 'create_task', 'title' => 'Kick-off call with {{ name }}', 'task_type' => 'call', 'due_in_days' => 1, 'delay' => 0, 'delay_unit' => 'minutes'],
                    ],
                ],
            ],
            'paid' => [
                'label' => __('Thank customers who pay'),
                'description' => __('When an invoice is paid, tag the contact and tell the team.'),
                'icon' => 'money-cash-bill',
                'values' => [
                    'name' => __('Thank customers who pay'),
                    'trigger' => 'invoice.paid',
                    'actions' => [
                        ['type' => 'add_tag', 'tags' => 'Paid customer', 'delay' => 0, 'delay_unit' => 'minutes'],
                        ['type' => 'notify', 'to' => '', 'subject' => 'Invoice paid by {{ name }}', 'message' => '', 'delay' => 0, 'delay_unit' => 'minutes'],
                    ],
                ],
            ],
            'vip' => [
                'label' => __('Follow up VIPs'),
                'description' => __('When someone is tagged VIP, schedule a personal call for their owner.'),
                'icon' => 'users',
                'values' => [
                    'name' => __('Follow up VIPs'),
                    'trigger' => 'contact.tagged',
                    'trigger_options' => ['tag' => 'VIP'],
                    'actions' => [
                        ['type' => 'create_task', 'title' => 'Personal call with {{ first_name }}', 'task_type' => 'call', 'due_in_days' => 2, 'priority' => 'high', 'delay' => 0, 'delay_unit' => 'minutes'],
                    ],
                ],
            ],
        ];
    }
}
