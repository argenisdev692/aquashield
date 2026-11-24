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

            $this->registrar->forgetCachedPermissions();

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
