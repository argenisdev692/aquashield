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
