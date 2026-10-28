<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ActivityLog\Http\Controllers\Api\ActivityLogController;

Route::middleware('api')->prefix('api')->group(function (): void {
    Route::middleware(['auth:sanctum', 'can:view-any-activity-logs'])->group(function (): void {
        Route::get('/activity', [ActivityLogController::class, 'index'])
            ->name('api.activity.index');

        Route::get('/activity/{activity}', [ActivityLogController::class, 'show'])
            ->whereUuid('activity')
            ->middleware('can:view-activity-log')
            ->name('api.activity.show');
    });
});
