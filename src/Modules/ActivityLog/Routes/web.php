<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ActivityLog\Http\Controllers\ActivityExportController;
use Modules\ActivityLog\Http\Controllers\ActivityLogController;

Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified'])
        ->prefix('activity')
        ->name('activity.')
        ->group(function (): void {
            Route::get('/', [ActivityLogController::class, 'index'])
                ->middleware('permission:VIEW_ANY_ACTIVITY_LOGS')
                ->name('index');

            // Static path before `{activity}`: the UUID constraint would make
            // `/activity/export/csv` a 404 if the order were reversed.
            Route::get('/export/{format}', ActivityExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware(['permission:EXPORT_ACTIVITY_LOGS', 'throttle:10,1'])
                ->name('export');

            Route::get('/{activity}', [ActivityLogController::class, 'show'])
                ->whereUuid('activity')
                ->middleware('permission:VIEW_ACTIVITY_LOGS')
                ->name('show');
        });
});
