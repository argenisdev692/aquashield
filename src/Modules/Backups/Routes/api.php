<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Backups\Http\Controllers\Api\BackupController;
use Modules\Backups\Http\Controllers\BackupDownloadController;

Route::middleware('api')->prefix('api')->group(function (): void {
    Route::middleware(['auth:sanctum', 'can:view-any-backups'])->group(function (): void {
        Route::get('/backups', [BackupController::class, 'index'])
            ->name('api.backups.index');

        Route::get('/backups/{backup}', [BackupController::class, 'show'])
            ->whereUuid('backup')
            ->middleware('can:view-backup')
            ->name('api.backups.show');

        // Reuses the web streamer so the audit entry, permission check and
        // content type cannot drift between the two transports.
        Route::get('/backups/{backup}/download', BackupDownloadController::class)
            ->whereUuid('backup')
            ->middleware(['can:download-backup', 'throttle:10,1'])
            ->name('api.backups.download');
    });
});
