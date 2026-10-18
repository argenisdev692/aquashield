<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

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
