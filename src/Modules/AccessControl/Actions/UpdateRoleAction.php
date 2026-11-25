<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Data\RoleInputData;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Enums\Role as RoleName;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renames a custom role and replaces its permission set.
 *
 * `SUPER_ADMIN` and `ADMIN` keep their names; their permission sets remain
 * editable because that is how an operator repairs a broken super admin.
 */
final readonly class UpdateRoleAction
{
    /**
     * The capabilities the installation owner needs to keep using this screen.
     *
     * A SUPER_ADMIN can otherwise remove UPDATE_ROLE from their own role and
     * lose the only UI that could restore it; provisioning is first-install
     * only, so there is no seeder path back.
     *
     * @var list<string>
     */
    private const array SUPER_ADMIN_REQUIRED_PERMISSIONS = [
        PermissionName::ViewAnyRole->value,
        PermissionName::CreateRole->value,
        PermissionName::UpdateRole->value,
        PermissionName::DeleteRole->value,
        PermissionName::ViewAnyPermission->value,
        PermissionName::CreatePermission->value,
        PermissionName::UpdatePermission->value,
        PermissionName::DeletePermission->value,
    ];

    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(Role $role, RoleInputData $data, User $actor): Role
    {
        return DB::transaction(function () use ($role, $data, $actor): Role {
            $locked = Role::query()
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousName = (string) $locked->name;
            $previousPermissions = $locked->permissions()
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->all();

            if ($data->name !== $previousName
                && (RoleData::isSystemName($previousName) || RoleData::isSystemName($data->name))) {
                throw ValidationException::withMessages([
                    'name' => 'System role names are reserved.',
                ]);
            }

            /*
             * Keep the installation owner able to repair itself. This is a
             * server invariant, not a UI courtesy: the role gate admits the
             * SUPER_ADMIN, but the route middleware also needs these names.
             */
            if ($previousName === RoleName::SuperAdmin->value) {
                $missing = array_values(array_diff(
                    self::SUPER_ADMIN_REQUIRED_PERMISSIONS,
                    $data->permissions,
                ));

                if ($missing !== []) {
                    throw ValidationException::withMessages([
                        'permissions' => 'The SUPER_ADMIN role must keep the access-management permissions: '.implode(', ', $missing).'.',
                    ]);
                }
            }

            try {
                $locked->update(['name' => $data->name]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'A role with this name already exists.',
                ]);
            }

            try {
                $locked->syncPermissions($data->permissions);
            } catch (PermissionDoesNotExist) {
                throw ValidationException::withMessages([
                    'permissions' => 'One or more permissions no longer exist.',
                ]);
            }

            // Spatie's own flush runs before this transaction commits; repair
            // the registry after the commit so a concurrent read cannot keep a
            // pre-commit snapshot for the cache TTL.
            DB::afterCommit(fn () => $this->registrar->forgetCachedPermissions());

            /*
             * Log the delta, not the count: "who removed EXPORT_ROLE from this
             * role" is the question the trail has to answer, and a count cannot.
             * Sorted so the property is deterministic regardless of payload
             * order. Names only — never ids, never PII.
             */
            $added = array_values(array_diff($data->permissions, $previousPermissions));
            $removed = array_values(array_diff($previousPermissions, $data->permissions));
            sort($added);
            sort($removed);

            $this->audit->log('role.updated', $locked, [
                'name' => $locked->name,
                'previous_name' => $previousName === $locked->name ? null : $previousName,
                'permissions_added' => $added,
                'permissions_removed' => $removed,
            ], $actor, 'access-control');

            return $locked->loadCount(['permissions', 'users']);
        });
    }
}
