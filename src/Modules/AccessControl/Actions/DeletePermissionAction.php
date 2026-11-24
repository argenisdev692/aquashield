<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes an unassigned custom permission.
 *
 * System permissions are code-referenced by route middleware and policies, and
 * an assigned permission would be silently detached from its roles/users, so
 * both cases are refused.
 */
final readonly class DeletePermissionAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(Permission $permission, User $actor): void
    {
        DB::transaction(function () use ($permission, $actor): void {
            $locked = Permission::query()
                ->whereKey($permission->getKey())
                ->withCount(['roles', 'users'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((bool) $locked->getAttribute('is_system')) {
                throw ValidationException::withMessages([
                    'permission' => 'System permissions cannot be deleted.',
                ]);
            }

            $rolesCount = (int) $locked->getAttribute('roles_count');
            $usersCount = (int) $locked->getAttribute('users_count');

            if ($rolesCount > 0 || $usersCount > 0) {
                throw ValidationException::withMessages([
                    'permission' => "This permission is assigned to {$rolesCount} role(s) and {$usersCount} user(s). Remove those grants before deleting it.",
                ]);
            }

            $this->audit->log('permission.deleted', $locked, [
                'name' => $locked->name,
            ], $actor, 'access-control');

            $locked->delete();

            $this->registrar->forgetCachedPermissions();
        });
    }
}
