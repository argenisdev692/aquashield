<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
    Route::get('/company-data', [CompanyDataController::class, 'show'])
        ->middleware(['auth', 'permission:VIEW_COMPANY_DATA'])
        ->name('company-data.show');

    Route::put('/company-data', [CompanyDataController::class, 'update'])
        ->middleware(['auth', 'permission:UPDATE_COMPANY_DATA'])
        ->name('company-data.update');
});
