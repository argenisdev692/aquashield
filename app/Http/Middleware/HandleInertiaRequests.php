<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Shared\Application\Queries\PendingCookieConsent;

class HandleInertiaRequests extends Middleware
{
    // NOTE: CSP/Vite nonce sync lives in Shared's SecurityHeaders middleware
    // (prepended in bootstrap/app.php) — not here. One owner, no duplication.
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // null whenever the visitor owes no decision, which is the common
            // case: one shared prop, typed from `CookieConsentData` via
            // `typescript:transform`, and the Vue banner renders nothing for it.
            'cookieConsent' => app(PendingCookieConsent::class)($request),
        ];
    }
}
