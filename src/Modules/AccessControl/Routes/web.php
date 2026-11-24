<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AccessControl\Http\Controllers\PermissionCacheController;
use Modules\AccessControl\Http\Controllers\PermissionController;
use Modules\AccessControl\Http\Controllers\PermissionExportController;
use Modules\AccessControl\Http\Controllers\RoleController;
use Modules\AccessControl\Http\Controllers\RoleExportController;
use Shared\Domain\Enums\Permission as PermissionName;

/*
 * The access catalogue is an installation-level surface. `can:manage-access`
 * (AccessControlServiceProvider) admits only an active SUPER_ADMIN; the
 * fine-grained permission checks are defence in depth and the same vocabulary
 * the FormRequests assert.
 */
Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified', 'can:manage-access', 'throttle:60,1'])->group(function (): void {
        Route::prefix('roles')->name('roles.')->group(function (): void {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('permission:'.PermissionName::ViewAnyRole->value)
                ->name('index');

            Route::get('/create', [RoleController::class, 'create'])
                ->middleware('permission:'.PermissionName::CreateRole->value)
                ->name('create');

            Route::post('/', [RoleController::class, 'store'])
                ->middleware('permission:'.PermissionName::CreateRole->value)
                ->name('store');

            // Static `export` before the dynamic `{role}` route.
            Route::get('/export/{format}', RoleExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware([
                    'permission:'.PermissionName::ViewAnyRole->value,
                    'permission:'.PermissionName::ExportRole->value,
                    'throttle:access-control-exports',
                ])
                ->name('export');

            Route::get('/{role}/edit', [RoleController::class, 'edit'])
                ->whereUuid('role')
                ->middleware('permission:'.PermissionName::UpdateRole->value)
                ->name('edit');

            Route::put('/{role}', [RoleController::class, 'update'])
                ->whereUuid('role')
                ->middleware('permission:'.PermissionName::UpdateRole->value)
                ->name('update');

            Route::delete('/{role}', [RoleController::class, 'destroy'])
                ->whereUuid('role')
                ->middleware('permission:'.PermissionName::DeleteRole->value)
                ->name('destroy');
        });

        Route::prefix('permissions')->name('permissions.')->group(function (): void {
            Route::get('/', [PermissionController::class, 'index'])
                ->middleware('permission:'.PermissionName::ViewAnyPermission->value)
                ->name('index');

            Route::post('/', [PermissionController::class, 'store'])
                ->middleware('permission:'.PermissionName::CreatePermission->value)
                ->name('store');

            Route::get('/export/{format}', PermissionExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware([
                    'permission:'.PermissionName::ViewAnyPermission->value,
                    'permission:'.PermissionName::ExportPermission->value,
                    'throttle:access-control-exports',
                ])
                ->name('export');

            Route::put('/{permission}', [PermissionController::class, 'update'])
                ->whereUuid('permission')
                ->middleware('permission:'.PermissionName::UpdatePermission->value)
                ->name('update');

            Route::delete('/{permission}', [PermissionController::class, 'destroy'])
                ->whereUuid('permission')
                ->middleware('permission:'.PermissionName::DeletePermission->value)
                ->name('destroy');
        });

        Route::post('/access-control/cache-reset', PermissionCacheController::class)
            ->middleware('permission:'.PermissionName::UpdatePermission->value)
            ->name('access-control.cache-reset');
    });
});
