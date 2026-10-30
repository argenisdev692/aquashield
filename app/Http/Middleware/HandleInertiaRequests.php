<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Auth\IdleSessionData;
use App\Data\Auth\UserData;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Shared\Application\Queries\PendingCookieConsent;
use Shared\Domain\Ports\StoragePort;

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
        $user = $request->user();

        /*
         * The shell filters its destinations by this list. It is a courtesy,
         * not a control: every route still re-checks its permission. JSON
         * clients never render the shell, so the permission graph is skipped
         * for them.
         */
        $permissions = $user instanceof User && ! $request->expectsJson()
            ? $user->getAllPermissions()
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->values()
                ->all()
            : [];

        return [
            ...parent::share($request),
            'authPermissions' => $permissions,
            // null whenever the visitor owes no decision, which is the common
            // case: one shared prop, typed from `CookieConsentData` via
            // `typescript:transform`, and the Vue banner renders nothing for it.
            'cookieConsent' => app(PendingCookieConsent::class)($request),

            // Null for guests. Authenticated pages mount the idle warning with
            // these two numbers; the countdown itself is client-side.
            'authIdle' => $user === null
                ? null
                : new IdleSessionData(
                    warnAfterSeconds: max(1, (int) config('auth.hardening.idle.warn_after_minutes', 5)) * 60,
                    graceSeconds: max(10, (int) config('auth.hardening.idle.grace_seconds', 60)),
                    keepAliveEndpoint: route('auth.keep-alive'),
                    logoutEndpoint: route('logout'),
                ),
            'user' => $user instanceof User
                ? UserData::fromModel($user, app(StoragePort::class))->toArray()
                : null,
        ];
    }
}
