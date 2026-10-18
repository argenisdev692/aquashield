<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Shared\Infrastructure\Http\Controllers\AcceptCookieConsentController;

/*
 * `appUrl` feeds the canonical/OG head tags on the entry page: scrapers
 * require absolute URLs, so the absolute origin can only come from the
 * server — `config('app.url')` is the single source. No Spatie Data class
 * is involved, which is why the prop is typed by hand on the page.
 */
Route::get('/', fn () => inertia('Welcome', ['appUrl' => config('app.url')]));

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
