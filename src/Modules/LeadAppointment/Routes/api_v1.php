<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| React Native / external admin API. Bearer token (Sanctum) + the same
| permission middleware as the web surface. The controllers, requests and
| permission checks are shared; only the authentication transport changes.
|
| Paths are /api/v1/* and Scramble documents them with the bearer scheme.
*/
Route::middleware(['api', 'auth:sanctum', 'verified', 'account.active', 'throttle:api'])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function (): void {
        (require __DIR__.'/admin.php')();
    });
