<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Backups\Http\Controllers\Api\BackupController;
use Shared\Domain\Enums\Permission as PermissionName;

/*
 * Read-only REST surface. Stateless (`api` middleware), Sanctum token,
 * documented by Scramble under /api, and guarded by the SAME Spatie
 * permissions as the web routes — never a second, Gate-shaped definition of a
 * permission name.
 *
 * Two endpoints on purpose. The archive download is deliberately absent: it is
 * a full copy of the database, and a stolen bearer token must not be able to
 * exfiltrate it. That one stays on `GET /backups/{uuid}/download`, behind the
 * session, CSRF and `DOWNLOAD_BACKUPS`.
 */
Route::middleware(['api', 'auth:sanctum', 'verified', 'account.active', 'throttle:api'])
    ->prefix('api')
    ->name('api.')
    ->group(function (): void {
        Route::get('/backups', [BackupController::class, 'index'])
            ->middleware('permission:'.PermissionName::ViewAnyBackup->value)
            ->name('backups.index');

        Route::get('/backups/{backup}', [BackupController::class, 'show'])
            ->whereUuid('backup')
            ->middleware('permission:'.PermissionName::ViewBackup->value)
            ->name('backups.show');
    });
