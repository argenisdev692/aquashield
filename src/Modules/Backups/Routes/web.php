<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Backups\Http\Controllers\BackupActivityController;
use Modules\Backups\Http\Controllers\BackupController;
use Modules\Backups\Http\Controllers\BackupDownloadController;
use Modules\Backups\Http\Controllers\BackupExportController;
use Shared\Domain\Enums\Permission as PermissionName;

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
                ->middleware('permission:'.PermissionName::ViewAnyBackup->value)
                ->name('index');

            Route::get('/activity', BackupActivityController::class)
                ->middleware('permission:'.PermissionName::ViewAnyBackup->value)
                ->name('activity');

            Route::post('/run', [BackupController::class, 'run'])
                ->middleware(['permission:'.PermissionName::CreateBackup->value, 'throttle:3,1'])
                ->name('run');

            Route::get('/run-status', [BackupController::class, 'runStatus'])
                ->middleware(['permission:'.PermissionName::ViewAnyBackup->value, 'throttle:30,1'])
                ->name('run-status');

            // Static paths precede `{backup}` so the UUID constraint cannot
            // swallow `bulk-delete` or `export`.
            Route::post('/bulk-delete', [BackupController::class, 'bulkDelete'])
                ->middleware('permission:'.PermissionName::BulkDeleteBackup->value)
                ->name('bulk-delete');

            Route::post('/bulk-restore', [BackupController::class, 'bulkRestore'])
                ->middleware('permission:'.PermissionName::BulkRestoreBackup->value)
                ->name('bulk-restore');

            Route::get('/export/{format}', BackupExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                // Same read capability as the index: EXPORT_BACKUPS alone must
                // never widen an inventory the actor cannot list.
                ->middleware([
                    'permission:'.PermissionName::ViewAnyBackup->value,
                    'permission:'.PermissionName::ExportBackup->value,
                    'throttle:10,1',
                ])
                ->name('export');

            Route::get('/{backup}', [BackupController::class, 'show'])
                ->whereUuid('backup')
                ->middleware('permission:'.PermissionName::ViewBackup->value)
                ->name('show');

            // The archive is a full database copy: DOWNLOAD_BACKUPS must never
            // widen an inventory the actor cannot see. Same pairing rule as
            // the export route above. `password.confirm` is the step-up gate:
            // a stolen session must not be enough to exfiltrate the database.
            Route::get('/{backup}/download', BackupDownloadController::class)
                ->whereUuid('backup')
                ->middleware([
                    'permission:'.PermissionName::ViewBackup->value,
                    'permission:'.PermissionName::DownloadBackup->value,
                    'password.confirm',
                    'throttle:10,1',
                ])
                ->name('download');

            Route::delete('/{backup}', [BackupController::class, 'destroy'])
                ->whereUuid('backup')
                ->middleware('permission:'.PermissionName::DeleteBackup->value)
                ->name('destroy');

            Route::patch('/{backup}/restore', [BackupController::class, 'restore'])
                ->whereUuid('backup')
                ->middleware('permission:'.PermissionName::RestoreBackup->value)
                ->name('restore');
        });
});
