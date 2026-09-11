<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'dodo' => [
        'api_key' => env('DODO_PAYMENTS_API_KEY'),
        'webhook_key' => env('DODO_PAYMENTS_WEBHOOK_KEY'),
        'environment' => env('DODO_PAYMENTS_ENVIRONMENT', 'live_mode'),
        'base_url' => env(
            'DODO_PAYMENTS_BASE_URL',
            env('DODO_PAYMENTS_ENVIRONMENT', 'live_mode') === 'test_mode'
                ? 'https://test.dodopayments.com'
                : 'https://live.dodopayments.com',
        ),
        'cloud_product_id' => env('DODO_PAYMENTS_CLOUD_PRODUCT_ID'),
        'currency' => env('DODO_PAYMENTS_CURRENCY', 'USD'),
        'grace_period_days' => (int) env('DODO_PAYMENTS_GRACE_PERIOD_DAYS', 7),
        'webhook_tolerance_seconds' => (int) env('DODO_PAYMENTS_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

];
