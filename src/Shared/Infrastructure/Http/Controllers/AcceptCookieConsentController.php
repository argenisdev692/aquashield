<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use Shared\Application\Queries\PendingCookieConsent;

/**
 * Records the visitor's cookie-consent decision.
 *
 * `spatie/laravel-cookie-consent` owns the CONTRACT — the cookie name, its
 * lifetime, the `enabled` switch and the `disableFor()` call that keeps the
 * cookie out of Laravel's cookie encryption. This controller owns the WRITE,
 * which the package would otherwise do from `document.cookie` inside its Blade
 * banner. Two things improve by moving it here:
 *
 * - **HttpOnly.** Nothing on the client needs to read the consent record; the
 *   server re-reads it on every request (see {@see PendingCookieConsent}).
 *   The package's inline script has to read it back, which is the only reason
 *   it asks for the cookie to stay unencrypted.
 * - **No `domain=` attribute when `SESSION_DOMAIN` is unset.** The package's
 *   JS writes `domain=<host>` explicitly, and a host with a port (Herd's
 *   `localhost:8000`) silently rejects the cookie — its own README calls this
 *   out. Passing `null` here means host-only, which is correct for a single
 *   origin and works on a port.
 */
final class AcceptCookieConsentController
{
    public function __invoke(): JsonResponse
    {
        // A tab loaded while the banner was on can still hold the endpoint. With
        // consent switched off the capability does not exist, so it must not
        // answer 200 and quietly write a record nobody will honour.
        abort_unless((bool) config('cookie-consent.enabled'), 404);

        $days = (int) config('cookie-consent.cookie_lifetime');

        Cookie::queue(
            Cookie::make(
                name: (string) config('cookie-consent.cookie_name'),
                // The package's own JS writes the literal `1`; keeping the value
                // means a record written by either implementation is honoured by
                // both the old Blade banner and this one.
                value: '1',
                minutes: $days * 24 * 60,
                path: '/',
                domain: config('session.domain'),
                // Same deployment switch as the session cookie, so consent is
                // Secure exactly when the rest of the app's cookies are.
                secure: (bool) config('session.secure'),
                httpOnly: true,
                sameSite: 'lax',
            ),
        );

        return response()->json(['consented' => true]);
    }
}
