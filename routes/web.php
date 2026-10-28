<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Modules\CompanyData\Application\Dtos\CompanyBrandingDto;
use Shared\Infrastructure\Company\CompanyProfile;
use Shared\Infrastructure\Http\Controllers\AcceptCookieConsentController;

/*
 * Entry screen for the internal operations workspace. The page is `noindex`
 * in the root Blade and carries no canonical/OG payload, so it needs no
 * `appUrl` prop — the old marketing head was the only consumer.
 */
Route::get('/', fn () => inertia('Welcome', [
    // Same public contract as GET /api/company-data, delivered with the page so
    // the header renders its real mark/name on first paint instead of flashing
    // the fallback while a client-side fetch resolves.
    'company' => CompanyBrandingDto::from(CompanyProfile::data()),
]));

/*
 * Google OAuth. `guest` keeps a signed-in visitor out of the handshake, and
 * the throttle caps the auth surface like Fortify's own rate limiters do.
 */
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])
        ->middleware('throttle:20,1')
        ->name('auth.google');

    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('auth.google.callback');
});

/*
 * Dashboard. Frontend-only placeholder for now: the page renders demo data
 * from a mock set and touches no backend. The board's real data source
 * (a leads pipeline) lands with the first module, and this route becomes an
 * authorized controller then.
 */
Route::middleware('auth')->get('/dashboard', fn () => inertia('Dashboard', [
    'user' => auth()->user(),
]));

/*
    Cookie consent. Public and unauthenticated by necessity: the whole point is
    to ask someone who has never signed in. It carries no payload, so there is
    nothing to validate, and CSRF is inherited from the `web` group - the Vue
    banner posts through Inertia's XHR client, which sends the XSRF header.

    The throttle is 30/min against a cookie a visitor sets once, so it only
    ever fires on abuse; the same reasoning as the Google handshake above.

    `route()` resolves this name into the `cookieConsent` Inertia prop, so the
    path is declared here exactly once.
*/
Route::post('/cookie-consent', AcceptCookieConsentController::class)
    ->middleware('throttle:30,1')
    ->name('cookie-consent.accept');
