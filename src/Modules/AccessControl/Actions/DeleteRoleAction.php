<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\RoleData;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes a custom role that is not assigned to any user.
 *
 * A hard delete is deliberate: the Spatie tables have no soft-delete contract,
 * and adding one would change authorization queries. Deleting a role with
 * users would silently strip their access, so it is refused with a count.
 */
final readonly class DeleteRoleAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(Role $role, User $actor): void
    {
        DB::transaction(function () use ($role, $actor): void {
            $locked = Role::query()
                ->whereKey($role->getKey())
                ->withCount(['permissions', 'users'])
                ->lockForUpdate()
                ->firstOrFail();

            if (RoleData::isSystemName((string) $locked->name)) {
                throw ValidationException::withMessages([
                    'role' => 'System roles cannot be deleted.',
                ]);
            }

            $usersCount = (int) $locked->getAttribute('users_count');

            if ($usersCount > 0) {
                throw ValidationException::withMessages([
                    'role' => "This role is assigned to {$usersCount} user(s). Reassign them before deleting it.",
                ]);
            }

            $this->audit->log('role.deleted', $locked, [
                'name' => $locked->name,
                'permissions_count' => (int) $locked->getAttribute('permissions_count'),
            ], $actor, 'access-control');

            $locked->delete();

            $this->registrar->forgetCachedPermissions();
        });
    }
}
