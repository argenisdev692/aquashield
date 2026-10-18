<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    |
    | Consumed by Shared\Infrastructure\Http\Middleware\SecurityHeaders.
    | All keys have code defaults in the middleware — this file makes the
    | policy explicit and reviewable instead of implicit.
    |
    */

    'headers' => [
        'permissions_policy' => env('PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),

        'hsts' => [
            'enabled' => env('HSTS_ENABLED', true),
            'max_age' => env('HSTS_MAX_AGE', 31536000),
            'include_subdomains' => env('HSTS_SUBDOMAINS', true),
            'preload' => env('HSTS_PRELOAD', false),
        ],

        'csp' => [
            // Extra sources appended to the nonce-strict script-src.
            'script_src' => [],
            // Pusher/Reverb, CDNs: same-origin is default, extend here.
            'connect_src' => [],
            'img_src' => [],
            'font_src' => [],
        ],
    ],

];
