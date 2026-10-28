<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API auth (Sanctum tokens)
|--------------------------------------------------------------------------
|
| The web app stays on Fortify sessions. This surface exists for mobile and
| external clients, and every response is a Spatie Data allowlist so Scramble
| documents the real contract. `auth:sanctum` activates Scramble's bearer
| security strategy (config/scramble.php).
|
*/
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('api.auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('api.auth.refresh');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

// Backwards-compatible alias for the old closure endpoint, now allowlisted.
Route::get('/user', [AuthController::class, 'me'])
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->name('api.user');
