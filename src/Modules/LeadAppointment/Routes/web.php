<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LeadAppointment\Http\Controllers\AppointmentController;
use Modules\LeadAppointment\Http\Controllers\LeadController;

/*
| Web admin surface. Session-authenticated, JSON responses for the DataTable
| kit and the calendar. The route definitions are shared with /api/v1 in
| admin.php so the permission matrix cannot drift between surfaces.
|
| The three page-only routes below complement the shared JSON surface: the
| list and detail endpoints answer both transports, while create/edit/board
| exist only for Inertia and therefore live on the web group alone.
*/
Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'verified'])->group(function (): void {
        (require __DIR__.'/admin.php')();

        Route::prefix('leads')->name('leads.')->group(function (): void {
            Route::get('/board', [LeadController::class, 'board'])
                ->middleware('permission:VIEW_ANY_LEAD')->name('board');
            Route::get('/create', [LeadController::class, 'create'])
                ->middleware('permission:CREATE_LEAD')->name('create');
            Route::get('/{uuid}/edit', [LeadController::class, 'edit'])
                ->whereUuid('uuid')
                ->middleware('permission:UPDATE_LEAD')->name('edit');
        });

        Route::prefix('appointments')->name('appointments.')->group(function (): void {
            Route::get('/create', [AppointmentController::class, 'create'])
                ->middleware('permission:CREATE_APPOINTMENT')->name('create');
            Route::get('/{uuid}/edit', [AppointmentController::class, 'edit'])
                ->whereUuid('uuid')
                ->middleware('permission:UPDATE_APPOINTMENT')->name('edit');
        });
    });
});
