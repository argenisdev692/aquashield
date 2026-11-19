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
            // PrimeVue/Volt draw their icon set as inline `data:image/svg+xml`
            // URIs. Without `data:` every icon in the app is blocked by CSP.
            //
            // `blob:` covers the two client-generated images: the avatar
            // cropper and the upload previews, both `URL.createObjectURL(file)`
            // assigned to an <img>. `'self'` does NOT match the blob scheme —
            // the browser logs "Refused to load the image 'blob:…'" and the
            // preview renders blank. Object URLs are same-origin and only this
            // app's nonce-strict bundle can mint one, so no third-party surface
            // is added.
            'img_src' => ['data:', 'blob:'],
            'font_src' => [],
        ],
    ],

];
