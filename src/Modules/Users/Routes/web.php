<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UserAccessController;
use Modules\Users\Http\Controllers\UserActivationController;
use Modules\Users\Http\Controllers\UserAvailabilityController;
use Modules\Users\Http\Controllers\UserController;
use Modules\Users\Http\Controllers\UserExportController;
use Shared\Domain\Enums\Permission as PermissionName;

/*
 * Staff management is a Super Admin surface.
 *
 * `can:manage-staff` is the single capability gate (defined in
 * UsersServiceProvider): a non-super-admin receives 403 before any route
 * middleware or controller runs. The fine-grained `permission:*` checks stay —
 * defence in depth, and the same capability family the FormRequests assert.
 *
 * The activation routes are public by necessity and sit OUTSIDE the group:
 * they are authorised by the signed URL plus the `invitation_sent_at`
 * single-use marker, not by a session.
 */
Route::middleware('web')->group(function (): void {
    Route::middleware(['guest', 'throttle:30,1'])
        ->get('/users/activate/{user:uuid}', [UserActivationController::class, 'show'])
        ->whereUuid('user')
        ->name('users.activation.show');

    Route::middleware(['guest', 'signed', 'throttle:5,1'])
        ->post('/users/activate/{user:uuid}', [UserActivationController::class, 'store'])
        ->whereUuid('user')
        ->name('users.activation.store');

    Route::middleware(['auth', 'verified', 'can:manage-staff', 'throttle:60,1'])
        ->prefix('users')
        ->name('users.')
        ->group(function (): void {
            Route::get('/', [UserController::class, 'index'])
                ->middleware('permission:'.PermissionName::ViewAnyUser->value)
                ->name('index');

            Route::get('/access-catalog', [UserAccessController::class, 'catalog'])
                ->middleware('permission:'.PermissionName::ViewAnyUser->value)
                ->name('access-catalog');

            /*
             * The debounced uniqueness probe. Throttled per IP because it is
             * an authenticated but high-frequency endpoint; the FormRequest
             * only permits the two allowlisted columns.
             */
            Route::get('/availability', UserAvailabilityController::class)
                ->middleware([
                    'permission:'.PermissionName::ViewAnyUser->value,
                    'throttle:user-availability',
                ])
                ->name('availability');

            // Static paths before `{user:uuid}`; the UUID constraint would make
            // `/users/create` a 404 otherwise.
            Route::get('/create', [UserController::class, 'create'])
                ->middleware('permission:'.PermissionName::CreateUser->value)
                ->name('create');

            Route::post('/', [UserController::class, 'store'])
                ->middleware('permission:'.PermissionName::CreateUser->value)
                ->name('store');

            Route::post('/bulk-delete', [UserController::class, 'bulkDelete'])
                ->middleware('permission:'.PermissionName::BulkDeleteUser->value)
                ->name('bulk-delete');

            Route::post('/bulk-restore', [UserController::class, 'bulkRestore'])
                ->middleware('permission:'.PermissionName::BulkRestoreUser->value)
                ->name('bulk-restore');

            Route::post('/bulk-status', [UserController::class, 'bulkStatus'])
                ->middleware('permission:'.PermissionName::UpdateUser->value)
                ->name('bulk-status');

            Route::post('/bulk-role', [UserController::class, 'bulkAssignRole'])
                ->middleware('permission:'.PermissionName::AssignRolesUser->value)
                ->name('bulk-role');

            Route::post('/bulk-invite', [UserController::class, 'bulkResendInvitations'])
                ->middleware([
                    'permission:'.PermissionName::UpdateUser->value,
                    'throttle:bulk-invitations',
                ])
                ->name('bulk-invite');

            /*
             * The export reads the same filters AND the same view permission
             * as the list. EXPORT_USER alone must never widen a list an actor
             * cannot read.
             */
            Route::get('/export/{format}', UserExportController::class)
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->middleware([
                    'permission:'.PermissionName::ViewAnyUser->value,
                    'permission:'.PermissionName::ExportUser->value,
                    'throttle:user-exports',
                ])
                ->name('export');

            Route::get('/{user:uuid}', [UserController::class, 'show'])
                ->withTrashed()
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::ViewUser->value)
                ->name('show');

            Route::get('/{user:uuid}/edit', [UserController::class, 'edit'])
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::UpdateUser->value)
                ->name('edit');

            Route::put('/{user:uuid}', [UserController::class, 'update'])
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::UpdateUser->value)
                ->name('update');

            Route::post('/{user:uuid}/password-reset', [UserController::class, 'sendPasswordReset'])
                ->whereUuid('user')
                ->middleware([
                    'permission:'.PermissionName::UpdateUser->value,
                    'throttle:user-password-resets',
                ])
                ->name('password-reset');

            Route::post('/{user:uuid}/invitation', [UserController::class, 'resendInvitation'])
                ->whereUuid('user')
                ->middleware([
                    'permission:'.PermissionName::UpdateUser->value,
                    'throttle:user-invitations',
                ])
                ->name('invitation.resend');

            Route::delete('/{user:uuid}', [UserController::class, 'destroy'])
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::DeleteUser->value)
                ->name('destroy');

            Route::patch('/{user:uuid}/restore', [UserController::class, 'restore'])
                ->withTrashed()
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::RestoreUser->value)
                ->name('restore');

            Route::put('/{user:uuid}/roles', [UserAccessController::class, 'assignRoles'])
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::AssignRolesUser->value)
                ->name('roles.update');

            Route::put('/{user:uuid}/permissions', [UserAccessController::class, 'assignPermissions'])
                ->whereUuid('user')
                ->middleware('permission:'.PermissionName::AssignPermissionsUser->value)
                ->name('permissions.update');
        });
});
