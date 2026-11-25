<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Data\RoleInputData;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Creates a role and its initial permission set in one transaction.
 *
 * The FormRequest validates transport input; this action re-asserts the
 * business invariant (system role names are reserved) so a seeder, command or
 * job cannot create a role the application treats as a system role.
 *
 * The unique constraint is the real duplicate guard: Spatie's `Role::create`
 * does a SELECT pre-check and then an INSERT, so two concurrent requests can
 * both pass the pre-check. Both the package exception and the database
 * exception are mapped to a 422 instead of a 500.
 */
final readonly class CreateRoleAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(RoleInputData $data, User $actor): Role
    {
        if (RoleData::isSystemName($data->name)) {
            throw ValidationException::withMessages([
                'name' => 'System role names are reserved.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor): Role {
            try {
                $role = $this->createRole($data);
            } catch (Throwable $exception) {
                if (! $exception instanceof RoleAlreadyExists
                    && ! $exception instanceof UniqueConstraintViolationException) {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'name' => 'A role with this name already exists.',
                ]);
            }

            $this->syncPermissions($role, $data->permissions);

            /*
             * Spatie flushes the registry on its own model writes, but that
             * flush happens inside this transaction. A concurrent reader can
             * repopulate the cache from the pre-commit snapshot and keep it for
             * the 24h TTL. Repair the cache after the commit instead.
             */
            DB::afterCommit(fn () => $this->registrar->forgetCachedPermissions());

            $this->audit->log('role.created', $role, [
                'name' => $role->name,
                'permissions_count' => count($data->permissions),
            ], $actor, 'access-control');

            return $role->loadCount(['permissions', 'users']);
        });
    }

    /**
     * Spatie's factory returns its contract; the configured model is ours.
     * The `instanceof` is a type guard, not a runtime expectation: a package
     * reconfiguration that points at a foreign role model is a programming
     * error, not a request the controller can recover from.
     *
     * @throws RoleAlreadyExists
     */
    private function createRole(RoleInputData $data): Role
    {
        $role = Role::create([
            'name' => $data->name,
            'guard_name' => 'web',
        ]);

        if (! $role instanceof Role) {
            throw new LogicException('The configured role model must extend '.Role::class.'.');
        }

        return $role;
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
