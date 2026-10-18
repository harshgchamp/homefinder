<?php

return [

    /*
    | Postcode lookup (App\Contracts\PostcodeLookup). driver: "postcodes_io" (the
    | real API) or "fake" (no network; used by the test suite).
    */
    'postcodes' => [
        'driver' => env('POSTCODES_DRIVER', 'postcodes_io'),
        'base_url' => env('POSTCODES_BASE_URL', 'https://api.postcodes.io'),
        'timeout' => (int) env('POSTCODES_TIMEOUT', 3),
    ],

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

];
