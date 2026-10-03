<?php

/*
 * Alp CRM. Most options live in the Control Panel (Tools → Addons → Alp CRM → Settings).
 * API secrets can be set here from your .env instead, so they stay out of version control;
 * a value here wins over the Control Panel setting.
 */
return [
    // Where client files are stored. Use a private disk.
    'files_disk' => env('ALP_CRM_FILES_DISK', 'local'),

    // Let webhooks and automation webhook steps call private or local addresses (e.g. during development).
    'allow_private_webhooks' => env('ALP_CRM_ALLOW_PRIVATE_WEBHOOKS', false),

    'secrets' => [
        'stripe_secret_key' => env('ALP_CRM_STRIPE_SECRET_KEY'),
        'stripe_webhook_secret' => env('ALP_CRM_STRIPE_WEBHOOK_SECRET'),
        'paypal_client_id' => env('ALP_CRM_PAYPAL_CLIENT_ID'),
        'paypal_secret' => env('ALP_CRM_PAYPAL_SECRET'),
        'mailchimp_api_key' => env('ALP_CRM_MAILCHIMP_API_KEY'),
        'kit_api_key' => env('ALP_CRM_KIT_API_KEY'),
        'aweber_client_id' => env('ALP_CRM_AWEBER_CLIENT_ID'),
        'aweber_client_secret' => env('ALP_CRM_AWEBER_CLIENT_SECRET'),
        'twilio_sid' => env('ALP_CRM_TWILIO_SID'),
        'twilio_token' => env('ALP_CRM_TWILIO_TOKEN'),
        'google_client_id' => env('ALP_CRM_GOOGLE_CLIENT_ID'),
        'google_client_secret' => env('ALP_CRM_GOOGLE_CLIENT_SECRET'),
    ],
];
