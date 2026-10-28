<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CompanyData\Http\Controllers\Api\CompanyDataController;

/*
 * Public, read-only API. Scramble documents it because config/scramble.php
 * matches the `api` path; the authenticated writes stay on the web session
 * routes in Routes/web.php, where Inertia already has CSRF and session state.
 */
Route::middleware('api')->prefix('api')->group(function (): void {
    Route::get('/company-data', [CompanyDataController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('api.company-data.show');

    // Sanctum is the API transport; the `can:` abilities are defined by the
    // module provider and resolve to the same Spatie permissions as the web
    // routes, so there is one authorisation source.
    Route::get('/company-data/manage', [CompanyDataController::class, 'showManage'])
        ->middleware(['auth:sanctum', 'can:view-company-data'])
        ->name('api.company-data.manage');

    Route::put('/company-data', [CompanyDataController::class, 'update'])
        ->middleware(['auth:sanctum', 'can:update-company-data'])
        ->name('api.company-data.update');
});
