<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LeadAppointment\Http\Controllers\AppointmentController;
use Modules\LeadAppointment\Http\Controllers\AppointmentExportController;
use Modules\LeadAppointment\Http\Controllers\AvailabilityController;
use Modules\LeadAppointment\Http\Controllers\AvailabilityExceptionExportController;
use Modules\LeadAppointment\Http\Controllers\CalendarController;
use Modules\LeadAppointment\Http\Controllers\CalendarEventsController;
use Modules\LeadAppointment\Http\Controllers\LeadController;
use Modules\LeadAppointment\Http\Controllers\LeadExportController;
use Modules\LeadAppointment\Http\Controllers\ReportController;
use Shared\Domain\Enums\Permission;

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
            ->middleware('permission:'.Permission::ViewAnyLead->value)->name('index');
        Route::post('/', [LeadController::class, 'store'])
            ->middleware('permission:'.Permission::CreateLead->value)->name('store');
        Route::post('/bulk-delete', [LeadController::class, 'bulkDelete'])
            ->middleware('permission:'.Permission::BulkDeleteLead->value)->name('bulk-delete');
        Route::post('/bulk-restore', [LeadController::class, 'bulkRestore'])
            ->middleware('permission:'.Permission::BulkRestoreLead->value)->name('bulk-restore');
        Route::get('/export/{format}', LeadExportController::class)
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            // The export re-checks the list capability: EXPORT_LEAD alone must
            // never widen a list the actor cannot read.
            ->middleware(['permission:'.Permission::ViewAnyLead->value, 'permission:'.Permission::ExportLead->value, 'throttle:10,1'])->name('export');
        Route::get('/{uuid}', [LeadController::class, 'show'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::ViewLead->value)->name('show');
        Route::put('/{uuid}', [LeadController::class, 'update'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateLead->value)->name('update');
        Route::delete('/{uuid}', [LeadController::class, 'destroy'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::DeleteLead->value)->name('destroy');
        Route::patch('/{uuid}/restore', [LeadController::class, 'restore'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::RestoreLead->value)->name('restore');
        Route::post('/{uuid}/status', [LeadController::class, 'updateStatus'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateLead->value)->name('status');
        Route::post('/{uuid}/activities', [LeadController::class, 'logActivity'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateLead->value)->name('activities.store');
        Route::post('/{uuid}/merge', [LeadController::class, 'merge'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateLead->value)->name('merge');
    });

    Route::prefix('appointments')->name('appointments.')->group(function (): void {
        Route::get('/', [AppointmentController::class, 'index'])
            ->middleware('permission:'.Permission::ViewAnyAppointment->value)->name('index');
        Route::get('/calendar', CalendarController::class)
            ->middleware('permission:'.Permission::ViewAnyAppointment->value)->name('calendar');
        Route::get('/calendar-events', CalendarEventsController::class)
            ->middleware('permission:'.Permission::ViewAnyAppointment->value)->name('calendar-events');
        Route::post('/', [AppointmentController::class, 'store'])
            ->middleware('permission:'.Permission::CreateAppointment->value)->name('store');
        Route::post('/bulk-delete', [AppointmentController::class, 'bulkDelete'])
            ->middleware('permission:'.Permission::BulkDeleteAppointment->value)->name('bulk-delete');
        Route::post('/bulk-restore', [AppointmentController::class, 'bulkRestore'])
            ->middleware('permission:'.Permission::BulkRestoreAppointment->value)->name('bulk-restore');
        Route::get('/export/{format}', AppointmentExportController::class)
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            ->middleware(['permission:'.Permission::ViewAnyAppointment->value, 'permission:'.Permission::ExportAppointment->value, 'throttle:10,1'])->name('export');
        Route::get('/{uuid}', [AppointmentController::class, 'show'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::ViewAppointment->value)->name('show');
        Route::delete('/{uuid}', [AppointmentController::class, 'destroy'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::DeleteAppointment->value)->name('destroy');
        Route::patch('/{uuid}/restore', [AppointmentController::class, 'restore'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::RestoreAppointment->value)->name('restore');
        Route::post('/{uuid}/confirm', [AppointmentController::class, 'confirm'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateAppointment->value)->name('confirm');
        Route::post('/{uuid}/reschedule', [AppointmentController::class, 'reschedule'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateAppointment->value)->name('reschedule');
        Route::post('/{uuid}/cancel', [AppointmentController::class, 'cancel'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateAppointment->value)->name('cancel');
        Route::post('/{uuid}/complete', [AppointmentController::class, 'complete'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateAppointment->value)->name('complete');
    });

    Route::prefix('availability-rules')->name('availability-rules.')->group(function (): void {
        Route::get('/', [AvailabilityController::class, 'rules'])
            ->middleware('permission:'.Permission::ViewAnyAvailabilityRule->value)->name('index');
        Route::post('/', [AvailabilityController::class, 'storeRule'])
            ->middleware('permission:'.Permission::CreateAvailabilityRule->value)->name('store');
        Route::post('/bulk-delete', [AvailabilityController::class, 'bulkDeleteRules'])
            ->middleware('permission:'.Permission::BulkDeleteAvailabilityRule->value)->name('bulk-delete');
        Route::post('/bulk-restore', [AvailabilityController::class, 'bulkRestoreRules'])
            ->middleware('permission:'.Permission::BulkRestoreAvailabilityRule->value)->name('bulk-restore');
        Route::put('/{uuid}', [AvailabilityController::class, 'updateRule'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateAvailabilityRule->value)->name('update');
        Route::delete('/{uuid}', [AvailabilityController::class, 'destroyRule'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::DeleteAvailabilityRule->value)->name('destroy');
        Route::patch('/{uuid}/restore', [AvailabilityController::class, 'restoreRule'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::RestoreAvailabilityRule->value)->name('restore');
    });

    Route::prefix('availability-exceptions')->name('availability-exceptions.')->group(function (): void {
        Route::get('/', [AvailabilityController::class, 'exceptions'])
            ->middleware('permission:'.Permission::ViewAnyAvailabilityException->value)->name('index');
        Route::post('/', [AvailabilityController::class, 'storeException'])
            ->middleware('permission:'.Permission::CreateAvailabilityException->value)->name('store');
        Route::post('/bulk-delete', [AvailabilityController::class, 'bulkDeleteExceptions'])
            ->middleware('permission:'.Permission::BulkDeleteAvailabilityException->value)->name('bulk-delete');
        Route::post('/bulk-restore', [AvailabilityController::class, 'bulkRestoreExceptions'])
            ->middleware('permission:'.Permission::BulkRestoreAvailabilityException->value)->name('bulk-restore');
        Route::post('/bulk-range', [AvailabilityController::class, 'bulkRange'])
            ->middleware(['permission:'.Permission::CreateAvailabilityException->value, 'throttle:10,1'])->name('bulk-range');
        Route::put('/{uuid}', [AvailabilityController::class, 'updateException'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::UpdateAvailabilityException->value)->name('update');
        Route::delete('/{uuid}', [AvailabilityController::class, 'destroyException'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::DeleteAvailabilityException->value)->name('destroy');
        Route::patch('/{uuid}/restore', [AvailabilityController::class, 'restoreException'])
            ->whereUuid('uuid')->middleware('permission:'.Permission::RestoreAvailabilityException->value)->name('restore');
    });

    Route::get('/availability/export/{format}', AvailabilityExceptionExportController::class)
        ->whereIn('format', ['csv', 'xlsx', 'pdf'])
        ->middleware([
            'permission:'.Permission::ViewAnyAvailabilityException->value,
            'permission:'.Permission::ExportAvailabilityException->value,
            'throttle:10,1',
        ])->name('availability.export');
    Route::get('/availability/calendar', [AvailabilityController::class, 'calendar'])
        ->middleware('permission:'.Permission::ViewAnyAvailabilityRule->value)->name('availability.calendar');
    Route::post('/availability/sync-holidays', [AvailabilityController::class, 'syncHolidays'])
        ->middleware(['permission:'.Permission::CreateAvailabilityException->value, 'throttle:10,1'])->name('availability.sync-holidays');

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/lead-sources', [ReportController::class, 'index'])
            ->middleware('permission:'.Permission::ViewAnyLead->value)->name('lead-sources');
        Route::get('/lead-sources/export/{format}', [ReportController::class, 'export'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            // The report page and its file read the same rows: same two gates.
            ->middleware(['permission:'.Permission::ViewAnyLead->value, 'permission:'.Permission::ExportLead->value, 'throttle:10,1'])->name('lead-sources.export');
    });
};
