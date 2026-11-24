<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LeadAppointment\Http\Controllers\AppointmentController;
use Modules\LeadAppointment\Http\Controllers\AppointmentExportController;
use Modules\LeadAppointment\Http\Controllers\AvailabilityController;
use Modules\LeadAppointment\Http\Controllers\CalendarController;
use Modules\LeadAppointment\Http\Controllers\LeadController;
use Modules\LeadAppointment\Http\Controllers\LeadExportController;
use Modules\LeadAppointment\Http\Controllers\ReportController;

/*
| Shared admin surface. Loaded twice:
| - web.php: session auth + verified
| - api_v1.php: Sanctum bearer + throttle:api
|
| Relative route names; the loader supplies the surface prefix. Keeping one
| definition means the web and React Native surfaces cannot drift.
*/
return function (): void {
    Route::prefix('leads')->name('leads.')->group(function (): void {
        Route::get('/', [LeadController::class, 'index'])
            ->middleware('permission:VIEW_ANY_LEAD')->name('index');
        Route::post('/', [LeadController::class, 'store'])
            ->middleware('permission:CREATE_LEAD')->name('store');
        Route::post('/bulk-delete', [LeadController::class, 'bulkDelete'])
            ->middleware('permission:BULK_DELETE_LEAD')->name('bulk-delete');
        Route::post('/bulk-restore', [LeadController::class, 'bulkRestore'])
            ->middleware('permission:BULK_RESTORE_LEAD')->name('bulk-restore');
        Route::get('/export/{format}', LeadExportController::class)
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            // The export re-checks the list capability: EXPORT_LEAD alone must
            // never widen a list the actor cannot read.
            ->middleware(['permission:VIEW_ANY_LEAD', 'permission:EXPORT_LEAD', 'throttle:10,1'])->name('export');
        Route::get('/{uuid}', [LeadController::class, 'show'])
            ->whereUuid('uuid')->middleware('permission:VIEW_LEAD')->name('show');
        Route::put('/{uuid}', [LeadController::class, 'update'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_LEAD')->name('update');
        Route::delete('/{uuid}', [LeadController::class, 'destroy'])
            ->whereUuid('uuid')->middleware('permission:DELETE_LEAD')->name('destroy');
        Route::patch('/{uuid}/restore', [LeadController::class, 'restore'])
            ->whereUuid('uuid')->middleware('permission:RESTORE_LEAD')->name('restore');
        Route::post('/{uuid}/status', [LeadController::class, 'updateStatus'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_LEAD')->name('status');
        Route::post('/{uuid}/activities', [LeadController::class, 'logActivity'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_LEAD')->name('activities.store');
        Route::post('/{uuid}/merge', [LeadController::class, 'merge'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_LEAD')->name('merge');
    });

    Route::prefix('appointments')->name('appointments.')->group(function (): void {
        Route::get('/', [AppointmentController::class, 'index'])
            ->middleware('permission:VIEW_ANY_APPOINTMENT')->name('index');
        Route::get('/calendar', CalendarController::class)
            ->middleware('permission:VIEW_ANY_APPOINTMENT')->name('calendar');
        Route::post('/', [AppointmentController::class, 'store'])
            ->middleware('permission:CREATE_APPOINTMENT')->name('store');
        Route::post('/bulk-delete', [AppointmentController::class, 'bulkDelete'])
            ->middleware('permission:BULK_DELETE_APPOINTMENT')->name('bulk-delete');
        Route::post('/bulk-restore', [AppointmentController::class, 'bulkRestore'])
            ->middleware('permission:BULK_RESTORE_APPOINTMENT')->name('bulk-restore');
        Route::get('/export/{format}', AppointmentExportController::class)
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            ->middleware(['permission:VIEW_ANY_APPOINTMENT', 'permission:EXPORT_APPOINTMENT', 'throttle:10,1'])->name('export');
        Route::get('/{uuid}', [AppointmentController::class, 'show'])
            ->whereUuid('uuid')->middleware('permission:VIEW_APPOINTMENT')->name('show');
        Route::delete('/{uuid}', [AppointmentController::class, 'destroy'])
            ->whereUuid('uuid')->middleware('permission:DELETE_APPOINTMENT')->name('destroy');
        Route::patch('/{uuid}/restore', [AppointmentController::class, 'restore'])
            ->whereUuid('uuid')->middleware('permission:RESTORE_APPOINTMENT')->name('restore');
        Route::post('/{uuid}/confirm', [AppointmentController::class, 'confirm'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_APPOINTMENT')->name('confirm');
        Route::post('/{uuid}/reschedule', [AppointmentController::class, 'reschedule'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_APPOINTMENT')->name('reschedule');
        Route::post('/{uuid}/cancel', [AppointmentController::class, 'cancel'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_APPOINTMENT')->name('cancel');
        Route::post('/{uuid}/complete', [AppointmentController::class, 'complete'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_APPOINTMENT')->name('complete');
    });

    Route::prefix('availability-rules')->name('availability-rules.')->group(function (): void {
        Route::get('/', [AvailabilityController::class, 'rules'])
            ->middleware('permission:VIEW_ANY_AVAILABILITY_RULE')->name('index');
        Route::post('/', [AvailabilityController::class, 'storeRule'])
            ->middleware('permission:CREATE_AVAILABILITY_RULE')->name('store');
        Route::post('/bulk-delete', [AvailabilityController::class, 'bulkDeleteRules'])
            ->middleware('permission:BULK_DELETE_AVAILABILITY_RULE')->name('bulk-delete');
        Route::post('/bulk-restore', [AvailabilityController::class, 'bulkRestoreRules'])
            ->middleware('permission:BULK_RESTORE_AVAILABILITY_RULE')->name('bulk-restore');
        Route::put('/{uuid}', [AvailabilityController::class, 'updateRule'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_AVAILABILITY_RULE')->name('update');
        Route::delete('/{uuid}', [AvailabilityController::class, 'destroyRule'])
            ->whereUuid('uuid')->middleware('permission:DELETE_AVAILABILITY_RULE')->name('destroy');
        Route::patch('/{uuid}/restore', [AvailabilityController::class, 'restoreRule'])
            ->whereUuid('uuid')->middleware('permission:RESTORE_AVAILABILITY_RULE')->name('restore');
    });

    Route::prefix('availability-exceptions')->name('availability-exceptions.')->group(function (): void {
        Route::get('/', [AvailabilityController::class, 'exceptions'])
            ->middleware('permission:VIEW_ANY_AVAILABILITY_EXCEPTION')->name('index');
        Route::post('/', [AvailabilityController::class, 'storeException'])
            ->middleware('permission:CREATE_AVAILABILITY_EXCEPTION')->name('store');
        Route::post('/bulk-delete', [AvailabilityController::class, 'bulkDeleteExceptions'])
            ->middleware('permission:BULK_DELETE_AVAILABILITY_EXCEPTION')->name('bulk-delete');
        Route::post('/bulk-restore', [AvailabilityController::class, 'bulkRestoreExceptions'])
            ->middleware('permission:BULK_RESTORE_AVAILABILITY_EXCEPTION')->name('bulk-restore');
        Route::post('/bulk-range', [AvailabilityController::class, 'bulkRange'])
            ->middleware(['permission:CREATE_AVAILABILITY_EXCEPTION', 'throttle:10,1'])->name('bulk-range');
        Route::put('/{uuid}', [AvailabilityController::class, 'updateException'])
            ->whereUuid('uuid')->middleware('permission:UPDATE_AVAILABILITY_EXCEPTION')->name('update');
        Route::delete('/{uuid}', [AvailabilityController::class, 'destroyException'])
            ->whereUuid('uuid')->middleware('permission:DELETE_AVAILABILITY_EXCEPTION')->name('destroy');
        Route::patch('/{uuid}/restore', [AvailabilityController::class, 'restoreException'])
            ->whereUuid('uuid')->middleware('permission:RESTORE_AVAILABILITY_EXCEPTION')->name('restore');
    });

    Route::get('/availability/calendar', [AvailabilityController::class, 'calendar'])
        ->middleware('permission:VIEW_ANY_AVAILABILITY_RULE')->name('availability.calendar');
    Route::post('/availability/sync-holidays', [AvailabilityController::class, 'syncHolidays'])
        ->middleware(['permission:CREATE_AVAILABILITY_EXCEPTION', 'throttle:10,1'])->name('availability.sync-holidays');

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/lead-sources', [ReportController::class, 'index'])
            ->middleware('permission:VIEW_ANY_LEAD')->name('lead-sources');
        Route::get('/lead-sources/export/{format}', [ReportController::class, 'export'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            // The report page and its file read the same rows: same two gates.
            ->middleware(['permission:VIEW_ANY_LEAD', 'permission:EXPORT_LEAD', 'throttle:10,1'])->name('lead-sources.export');
    });
};
