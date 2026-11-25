<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatorLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\KeepSessionAliveController;
use App\Http\Controllers\Auth\OneTimePasswordLoginController;
use App\Http\Controllers\SavedViewController;
use App\Http\Controllers\Settings\PasskeyPromptController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilePhotoController;
use Illuminate\Support\Facades\Route;
use Modules\CompanyData\Data\CompanyBrandingDto;
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
 * Passwordless email login. Send and verify are separate steps because the
 * address is held in the session between them; both are throttled by named
 * limiters in FortifyServiceProvider. `guest` keeps a signed-in visitor out.
 */
Route::middleware('guest')->group(function () {
    Route::get('/login/otp', [OneTimePasswordLoginController::class, 'create'])
        ->name('otp.login');

    Route::post('/login/otp', [OneTimePasswordLoginController::class, 'send'])
        ->middleware('throttle:otp-send')
        ->name('otp.send');

    Route::post('/login/otp/verify', [OneTimePasswordLoginController::class, 'verify'])
        ->middleware('throttle:otp-verify')
        ->name('otp.verify');

    Route::post('/login/authenticator', [AuthenticatorLoginController::class, 'store'])
        ->middleware('throttle:authenticator-login')
        ->name('login.authenticator');
});

/*
 * Account screen. The writes stay on Fortify's routes; this page is the
 * Inertia shell around them, so it needs no permission beyond `auth`.
 */
Route::middleware('auth')->group(function (): void {
    /*
     * Idle-session heartbeat. The React idle dialog posts here when the user
     * answers "Keep me signed in"; the route is throttled because a compromised
     * page should not be able to refresh the session in a tight loop.
     */
    Route::post('/auth/keep-alive', KeepSessionAliveController::class)
        ->middleware('throttle:60,1')
        ->name('auth.keep-alive');

    /*
     * The settings entry point. The hub of cards is gone: every account
     * surface lives in the tabbed screen, so `/settings` is a permanent alias
     * for it and old bookmarks still land on the real page.
     */
    Route::redirect('/settings', '/settings/profile')
        ->name('settings.index');

    Route::get('/settings/profile', [ProfileController::class, 'edit'])
        ->name('settings.profile.edit');
    Route::post('/settings/profile/photo', [ProfilePhotoController::class, 'store'])
        ->name('settings.profile.photo.store');
    Route::delete('/settings/profile/photo', [ProfilePhotoController::class, 'destroy'])
        ->name('settings.profile.photo.destroy');

    /*
     * Dismissing the "add a passkey" prompt is a courtesy write: throttled so a
     * compromised page cannot hammer the users table, and separate from the
     * settings page so it never triggers a full Inertia visit.
     */
    Route::post('/settings/passkey-prompt/dismiss', [PasskeyPromptController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('settings.passkey-prompt.dismiss');
});

/*
 * Personal saved views for the CRUD tables. JSON only: the table reads them
 * with TanStack Query and a save/delete is not a page visit.
 *
 * Ownership is enforced in the controller by scoping every query to the
 * authenticated user, so a forged UUID is a 404 rather than a read or delete.
 * The throttle bounds a user-written collection that is replayed into list
 * URLs.
 */
Route::middleware(['auth', 'throttle:60,1'])
    ->prefix('saved-views')
    ->name('saved-views.')
    ->group(function (): void {
        Route::get('/', [SavedViewController::class, 'index'])->name('index');

        Route::post('/', [SavedViewController::class, 'store'])->name('store');

        Route::delete('/{savedView}', [SavedViewController::class, 'destroy'])
            ->whereUuid('savedView')
            ->name('destroy');
    });

/*
    Cookie consent. Public and unauthenticated by necessity: the whole point is
    to ask someone who has never signed in. It carries no payload, so there is
    nothing to validate, and CSRF is inherited from the `web` group - the React
    banner posts through Inertia's XHR client, which sends the XSRF header.

    The throttle is 30/min against a cookie a visitor sets once, so it only
    ever fires on abuse; the same reasoning as the Google handshake above.

    `route()` resolves this name into the `cookieConsent` Inertia prop, so the
    path is declared here exactly once.
*/
Route::post('/cookie-consent', AcceptCookieConsentController::class)
    ->middleware('throttle:30,1')
    ->name('cookie-consent.accept');
