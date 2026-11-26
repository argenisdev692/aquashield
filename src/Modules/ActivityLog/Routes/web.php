<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ActivityLog\Http\Controllers\ActivityExportController;
use Modules\ActivityLog\Http\Controllers\ActivityLogController;
use Shared\Domain\Enums\Permission as PermissionName;

Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified'])
        ->prefix('activity')
        ->name('activity.')
        ->group(function (): void {
            Route::get('/', [ActivityLogController::class, 'index'])
                ->middleware([
                    'permission:'.PermissionName::ViewAnyActivityLog->value,
                    'throttle:60,1',
                ])
                ->name('index');

            // Static path before `{activity}`: the UUID constraint would make
            // `/activity/export/csv` a 404 if the order were reversed.
            Route::get('/export/{format}', ActivityExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware([
                    'permission:'.PermissionName::ViewAnyActivityLog->value,
                    'permission:'.PermissionName::ExportActivityLog->value,
                    'throttle:10,1',
                ])
                ->name('export');

            Route::get('/{activity}', [ActivityLogController::class, 'show'])
                ->whereUuid('activity')
                ->middleware([
                    'permission:'.PermissionName::ViewActivityLog->value,
                    'throttle:60,1',
                ])
                ->name('show');
        });
});
