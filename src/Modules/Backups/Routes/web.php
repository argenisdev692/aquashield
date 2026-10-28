<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Backups\Http\Controllers\BackupActivityController;
use Modules\Backups\Http\Controllers\BackupController;
use Modules\Backups\Http\Controllers\BackupDownloadController;
use Modules\Backups\Http\Controllers\BackupExportController;

/*
 * `loadRoutesFrom()` only includes this file; it does not add the web group.
 * Without `web` the session is missing and every route redirects to login.
 */
Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified'])
        ->prefix('backups')
        ->name('backups.')
        ->group(function (): void {
            Route::get('/', [BackupController::class, 'index'])
                ->middleware('permission:VIEW_ANY_BACKUPS')
                ->name('index');

            Route::get('/activity', BackupActivityController::class)
                ->middleware('permission:VIEW_ANY_BACKUPS')
                ->name('activity');

            Route::post('/run', [BackupController::class, 'run'])
                ->middleware(['permission:CREATE_BACKUPS', 'throttle:3,1'])
                ->name('run');

            // Static paths precede `{backup}` so the UUID constraint cannot
            // swallow `bulk-delete` or `export`.
            Route::post('/bulk-delete', [BackupController::class, 'bulkDelete'])
                ->middleware('permission:BULK_DELETE_BACKUPS')
                ->name('bulk-delete');

            Route::post('/bulk-restore', [BackupController::class, 'bulkRestore'])
                ->middleware('permission:BULK_RESTORE_BACKUPS')
                ->name('bulk-restore');

            Route::get('/export/{format}', BackupExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware(['permission:EXPORT_BACKUPS', 'throttle:10,1'])
                ->name('export');

            Route::get('/{backup}', [BackupController::class, 'show'])
                ->whereUuid('backup')
                ->middleware('permission:VIEW_BACKUPS')
                ->name('show');

            Route::get('/{backup}/download', BackupDownloadController::class)
                ->whereUuid('backup')
                ->middleware(['permission:DOWNLOAD_BACKUPS', 'throttle:10,1'])
                ->name('download');

            Route::delete('/{backup}', [BackupController::class, 'destroy'])
                ->whereUuid('backup')
                ->middleware('permission:DELETE_BACKUPS')
                ->name('destroy');

            Route::patch('/{backup}/restore', [BackupController::class, 'restore'])
                ->whereUuid('backup')
                ->middleware('permission:RESTORE_BACKUPS')
                ->name('restore');
        });
});
