<?php

declare(strict_types=1);

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),

        // Browser key for the Maps JavaScript API + Places API (New).
        // Restrict it by HTTP referrer in Google Cloud; never treat it as a
        // server secret, because it is delivered to the browser by design.
        'maps_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    | Coarse IP → "City, Region, Country" enrichment for security emails.
    | The host is configuration, never request input. Disable it in tests or
    | on an air-gapped deployment; the alert still carries IP + device.
    */
    'geolocation' => [
        'enabled' => env('GEOLOCATION_ENABLED', true),
        'endpoint' => env('GEOLOCATION_ENDPOINT', 'https://ipwho.is'),
        'connect_timeout' => env('GEOLOCATION_CONNECT_TIMEOUT', 1),
        'timeout' => env('GEOLOCATION_TIMEOUT', 2),
    ],

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
