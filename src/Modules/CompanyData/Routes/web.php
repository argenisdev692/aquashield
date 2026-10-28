<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CompanyData\Http\Controllers\CompanyAssetController;
use Modules\CompanyData\Http\Controllers\CompanyDataController;

/*
 * Company data is a singleton: it is provisioned by the seeder, read with
 * VIEW_COMPANY_DATA and replaced in place with UPDATE_COMPANY_DATA. There is
 * deliberately no create or list verb — `SINGLETON_MODULES` in
 * RolePermissionSeeder owns that contract.
 *
 * The `web` group is applied here because `loadRoutesFrom()` only includes the
 * file; it does not inherit the framework's web middleware (session, CSRF,
 * cookie encryption). Without this group the `auth` middleware has no session
 * to authenticate against.
 */
Route::middleware('web')->group(function (): void {
    // The screen. `show` below stays the JSON contract for non-Inertia
    // clients; `edit` is the one Inertia entry point for the singleton.
    Route::get('/company-data/edit', [CompanyDataController::class, 'edit'])
        ->middleware(['auth', 'permission:VIEW_COMPANY_DATA'])
        ->name('company-data.edit');

    Route::get('/company-data', [CompanyDataController::class, 'show'])
        ->middleware(['auth', 'permission:VIEW_COMPANY_DATA'])
        ->name('company-data.show');

    Route::put('/company-data', [CompanyDataController::class, 'update'])
        ->middleware(['auth', 'permission:UPDATE_COMPANY_DATA'])
        ->name('company-data.update');

    /*
     * Brand assets. `{asset}` is the CompanyAsset enum, so an unknown value is
     * a 404 from route binding and the controller never parses a raw string.
     * The throttle is per-user and generous for a settings screen: it only
     * stops a scripted re-upload loop, which is the expensive path here.
     */
    Route::post('/company-data/assets/{asset}', [CompanyAssetController::class, 'store'])
        ->middleware(['auth', 'permission:UPDATE_COMPANY_DATA', 'throttle:30,1'])
        ->name('company-data.assets.store');

    Route::delete('/company-data/assets/{asset}', [CompanyAssetController::class, 'destroy'])
        ->middleware(['auth', 'permission:UPDATE_COMPANY_DATA', 'throttle:30,1'])
        ->name('company-data.assets.destroy');
});
