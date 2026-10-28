<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UserAccessController;
use Modules\Users\Http\Controllers\UserController;
use Modules\Users\Http\Controllers\UserExportController;

/*
 * Admin user management. Session-authenticated because the eventual screen is
 * Inertia; the same routes answer JSON for the DataTable kit.
 *
 * The `web` group is applied explicitly because `loadRoutesFrom()` only
 * includes the file; it does not inherit the framework's web middleware
 * (session, CSRF, cookie encryption, SubstituteBindings). Without it
 * `{user:uuid}` resolves from the container as an empty model instead of
 * binding the row — a 404 on every write and a silently empty read.
 *
 * Every mutating verb carries its own permission, and the row-level half
 * (ceiling, self-protection, last super admin) is enforced by UserPolicy inside
 * the action. Route order matters: the static paths precede `{user:uuid}` so
 * `access-catalog` and `export` are never captured as UUIDs.
 */
Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified'])
        ->prefix('users')
        ->name('users.')
        ->group(function (): void {
            Route::get('/', [UserController::class, 'index'])
                ->middleware('permission:VIEW_ANY_USER')
                ->name('index');

            Route::get('/access-catalog', [UserAccessController::class, 'catalog'])
                ->middleware('permission:VIEW_ANY_USER')
                ->name('access-catalog');

            Route::post('/', [UserController::class, 'store'])
                ->middleware('permission:CREATE_USER')
                ->name('store');

            Route::post('/bulk-delete', [UserController::class, 'bulkDelete'])
                ->middleware('permission:BULK_DELETE_USER')
                ->name('bulk-delete');

            Route::post('/bulk-restore', [UserController::class, 'bulkRestore'])
                ->middleware('permission:BULK_RESTORE_USER')
                ->name('bulk-restore');

            Route::get('/export/{format}', UserExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware(['permission:EXPORT_USER', 'throttle:10,1'])
                ->name('export');

            Route::get('/{user:uuid}', [UserController::class, 'show'])
                ->whereUuid('user')
                ->middleware('permission:VIEW_USER')
                ->name('show');

            Route::put('/{user:uuid}', [UserController::class, 'update'])
                ->whereUuid('user')
                ->middleware('permission:UPDATE_USER')
                ->name('update');

            Route::delete('/{user:uuid}', [UserController::class, 'destroy'])
                ->whereUuid('user')
                ->middleware('permission:DELETE_USER')
                ->name('destroy');

            Route::patch('/{user:uuid}/restore', [UserController::class, 'restore'])
                ->withTrashed()
                ->whereUuid('user')
                ->middleware('permission:RESTORE_USER')
                ->name('restore');

            Route::put('/{user:uuid}/roles', [UserAccessController::class, 'assignRoles'])
                ->whereUuid('user')
                ->middleware('permission:ASSIGN_ROLES_USER')
                ->name('roles.update');

            Route::put('/{user:uuid}/permissions', [UserAccessController::class, 'assignPermissions'])
                ->whereUuid('user')
                ->middleware('permission:ASSIGN_PERMISSIONS_USER')
                ->name('permissions.update');
        });
});
