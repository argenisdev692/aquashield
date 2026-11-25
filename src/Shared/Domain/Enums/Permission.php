<?php

declare(strict_types=1);

namespace Shared\Domain\Enums;

/**
 * Capability names used by the authorization code.
 *
 * The seeder still creates the whole application permission matrix; this enum
 * is the single source for the names that appear in a route middleware, policy,
 * form request or controller capability. A typo can no longer silently drift
 * between those layers.
 */
enum Permission: string
{
    case ViewAnyUser = 'VIEW_ANY_USER';
    case ViewUser = 'VIEW_USER';
    case CreateUser = 'CREATE_USER';
    case UpdateUser = 'UPDATE_USER';
    case DeleteUser = 'DELETE_USER';
    case RestoreUser = 'RESTORE_USER';
    case BulkDeleteUser = 'BULK_DELETE_USER';
    case BulkRestoreUser = 'BULK_RESTORE_USER';
    case ExportUser = 'EXPORT_USER';
    case AssignRolesUser = 'ASSIGN_ROLES_USER';
    case AssignPermissionsUser = 'ASSIGN_PERMISSIONS_USER';

    case ViewAnyRole = 'VIEW_ANY_ROLE';
    case CreateRole = 'CREATE_ROLE';
    case UpdateRole = 'UPDATE_ROLE';
    case DeleteRole = 'DELETE_ROLE';
    case ExportRole = 'EXPORT_ROLE';

    case ViewAnyPermission = 'VIEW_ANY_PERMISSION';
    case CreatePermission = 'CREATE_PERMISSION';
    case UpdatePermission = 'UPDATE_PERMISSION';
    case DeletePermission = 'DELETE_PERMISSION';
    case ExportPermission = 'EXPORT_PERMISSION';

    case ViewAnyActivityLog = 'VIEW_ANY_ACTIVITY_LOGS';
    case ViewActivityLog = 'VIEW_ACTIVITY_LOGS';
    case ExportActivityLog = 'EXPORT_ACTIVITY_LOGS';

    case ViewAnyLead = 'VIEW_ANY_LEAD';
    case ViewLead = 'VIEW_LEAD';
    case CreateLead = 'CREATE_LEAD';
    case UpdateLead = 'UPDATE_LEAD';
    case DeleteLead = 'DELETE_LEAD';
    case RestoreLead = 'RESTORE_LEAD';
    case BulkDeleteLead = 'BULK_DELETE_LEAD';
    case BulkRestoreLead = 'BULK_RESTORE_LEAD';
    case ExportLead = 'EXPORT_LEAD';

    case ViewAnyAppointment = 'VIEW_ANY_APPOINTMENT';
    case ViewAppointment = 'VIEW_APPOINTMENT';
    case CreateAppointment = 'CREATE_APPOINTMENT';
    case UpdateAppointment = 'UPDATE_APPOINTMENT';
    case DeleteAppointment = 'DELETE_APPOINTMENT';
    case RestoreAppointment = 'RESTORE_APPOINTMENT';
    case BulkDeleteAppointment = 'BULK_DELETE_APPOINTMENT';
    case BulkRestoreAppointment = 'BULK_RESTORE_APPOINTMENT';
    case ExportAppointment = 'EXPORT_APPOINTMENT';

    case ViewAnyAvailabilityRule = 'VIEW_ANY_AVAILABILITY_RULE';
    case CreateAvailabilityRule = 'CREATE_AVAILABILITY_RULE';
    case UpdateAvailabilityRule = 'UPDATE_AVAILABILITY_RULE';
    case DeleteAvailabilityRule = 'DELETE_AVAILABILITY_RULE';
    case RestoreAvailabilityRule = 'RESTORE_AVAILABILITY_RULE';
    case BulkDeleteAvailabilityRule = 'BULK_DELETE_AVAILABILITY_RULE';
    case BulkRestoreAvailabilityRule = 'BULK_RESTORE_AVAILABILITY_RULE';

    case ViewAnyAvailabilityException = 'VIEW_ANY_AVAILABILITY_EXCEPTION';
    case CreateAvailabilityException = 'CREATE_AVAILABILITY_EXCEPTION';
    case UpdateAvailabilityException = 'UPDATE_AVAILABILITY_EXCEPTION';
    case DeleteAvailabilityException = 'DELETE_AVAILABILITY_EXCEPTION';
    case RestoreAvailabilityException = 'RESTORE_AVAILABILITY_EXCEPTION';
    case BulkDeleteAvailabilityException = 'BULK_DELETE_AVAILABILITY_EXCEPTION';
    case BulkRestoreAvailabilityException = 'BULK_RESTORE_AVAILABILITY_EXCEPTION';
    case ExportAvailabilityException = 'EXPORT_AVAILABILITY_EXCEPTION';

    /**
     * @return list<string>
     */
    public static function userValues(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
