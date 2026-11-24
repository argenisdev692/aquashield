<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\RoleInputData;
use RuntimeException;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a role and its initial permission set in one transaction.
 *
 * The FormRequest has already validated existence; the catch covers the race
 * between validation and `syncPermissions()` (a permission deleted in between).
 */
final readonly class CreateRoleAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(RoleInputData $data, User $actor): Role
    {
        return DB::transaction(function () use ($data, $actor): Role {
            try {
                $created = Role::create([
                    'name' => $data->name,
                    'guard_name' => 'web',
                ]);
            } catch (RoleAlreadyExists) {
                throw ValidationException::withMessages([
                    'name' => 'A role with this name already exists.',
                ]);
            }

            if (! $created instanceof Role) {
                throw new RuntimeException('The configured role model did not return a Spatie role.');
            }

            $role = $created;

            $this->syncPermissions($role, $data->permissions);

            $this->registrar->forgetCachedPermissions();

            $this->audit->log('role.created', $role, [
                'name' => $role->name,
                'permissions_count' => count($data->permissions),
            ], $actor, 'access-control');

            return $role->loadCount(['permissions', 'users']);
        });
    }

    /**
     * @param list<string> $permissions
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        try {
            $role->syncPermissions($permissions);
        } catch (PermissionDoesNotExist) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permissions no longer exist.',
            ]);
        }
    }
}
