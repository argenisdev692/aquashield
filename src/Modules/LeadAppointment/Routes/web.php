<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LeadAppointment\Http\Controllers\AppointmentController;
use Modules\LeadAppointment\Http\Controllers\AvailabilityPageController;
use Modules\LeadAppointment\Http\Controllers\DashboardController;
use Modules\LeadAppointment\Http\Controllers\LeadController;
use Shared\Domain\Enums\Permission;

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

        /*
         * Dashboard. One server read: the same lead columns as `/leads/board`
         * plus the current month of appointments. The client never owns a
         * second copy of the pipeline. It lives in the module because the
         * controller does.
         */
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('leads')->name('leads.')->group(function (): void {
            Route::get('/board', [LeadController::class, 'board'])
                ->middleware('permission:'.Permission::ViewAnyLead->value)->name('board');
            Route::get('/create', [LeadController::class, 'create'])
                ->middleware('permission:'.Permission::CreateLead->value)->name('create');
            Route::get('/{uuid}/edit', [LeadController::class, 'edit'])
                ->whereUuid('uuid')
                ->middleware(['permission:'.Permission::UpdateLead->value, 'permission:'.Permission::ViewLead->value])->name('edit');
        });

        Route::prefix('appointments')->name('appointments.')->group(function (): void {
            Route::get('/create', [AppointmentController::class, 'create'])
                ->middleware([
                    'permission:'.Permission::CreateAppointment->value,
                    'permission:'.Permission::ViewAnyLead->value,
                    'permission:'.Permission::ViewAppointment->value,
                ])->name('create');
            Route::get('/{uuid}/edit', [AppointmentController::class, 'edit'])
                ->whereUuid('uuid')
                ->middleware(['permission:'.Permission::UpdateAppointment->value, 'permission:'.Permission::ViewAppointment->value])->name('edit');
        });

        /*
         * Availability screen set. The JSON resource stays on
         * `/availability-exceptions` for the calendar widget and the mobile
         * client; these routes own the Inertia visit + redirect contract, so a
         * page write never has to answer a JSON response.
         */
        Route::prefix('availability')->name('availability.')->group(function (): void {
            Route::get('/', [AvailabilityPageController::class, 'index'])
                ->middleware('permission:'.Permission::ViewAnyAvailabilityException->value)->name('index');
            /*
             * The month grid links every day to "create": the resolved day has
             * no uuid, so the controller is what turns the visit into an edit
             * when a row already exists for that date. An update-only editor
             * therefore needs to reach this READ route — `permission:a|b` is
             * Spatie's any-of form. The write route below stays CREATE-only, so
             * this widens navigation, never authorisation.
             */
            Route::get('/create', [AvailabilityPageController::class, 'create'])
                ->middleware('permission:'.Permission::CreateAvailabilityException->value.'|'.Permission::UpdateAvailabilityException->value)->name('create');
            Route::post('/', [AvailabilityPageController::class, 'store'])
                ->middleware('permission:'.Permission::CreateAvailabilityException->value)->name('store');
            Route::get('/{uuid}/edit', [AvailabilityPageController::class, 'edit'])
                ->whereUuid('uuid')
                ->middleware('permission:'.Permission::UpdateAvailabilityException->value)->name('edit');
            Route::put('/{uuid}', [AvailabilityPageController::class, 'update'])
                ->whereUuid('uuid')
                ->middleware('permission:'.Permission::UpdateAvailabilityException->value)->name('update');
            Route::delete('/{uuid}', [AvailabilityPageController::class, 'destroy'])
                ->whereUuid('uuid')
                ->middleware('permission:'.Permission::DeleteAvailabilityException->value)->name('destroy');
            Route::patch('/{uuid}/restore', [AvailabilityPageController::class, 'restore'])
                ->whereUuid('uuid')
                ->middleware('permission:'.Permission::RestoreAvailabilityException->value)->name('restore');
        });
    });
});
