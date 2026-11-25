<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\AccessControl\Data\PermissionInputData;
use Shared\Domain\Enums\Permission as PermissionEnum;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Creates a custom permission (`is_system = false`). The request can never
 * forge the system flag.
 *
 * The reserved-name invariant is re-asserted here, not only in the FormRequest:
 * a seeder, command or job calling the action directly must not create a name
 * the application checks in code.
 *
 * Spatie's `Permission::create` pre-checks by name and then inserts; two
 * concurrent requests can both pass the pre-check. Both the package exception
 * and the database unique violation become a 422.
 */
final readonly class CreatePermissionAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(PermissionInputData $data, User $actor): Permission
    {
        if (in_array($data->name, PermissionEnum::userValues(), true)) {
            throw ValidationException::withMessages([
                'name' => 'This permission name is reserved by the application.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor): Permission {
            try {
                $permission = $this->createPermission($data);
            } catch (Throwable $exception) {
                if (! $exception instanceof PermissionAlreadyExists
                    && ! $exception instanceof UniqueConstraintViolationException) {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'name' => 'A permission with this name already exists.',
                ]);
            }

            // See CreateRoleAction: Spatie's pre-commit flush is not enough.
            DB::afterCommit(fn () => $this->registrar->forgetCachedPermissions());

            $this->audit->log('permission.created', $permission, [
                'name' => $permission->name,
            ], $actor, 'access-control');

            $permission->loadCount(['roles', 'users']);

            return $permission;
        });
    }

    /**
     * @throws PermissionAlreadyExists
     */
    private function createPermission(PermissionInputData $data): Permission
    {
        $permission = Permission::create([
            'name' => $data->name,
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        if (! $permission instanceof Permission) {
            throw new LogicException('The configured permission model must extend '.Permission::class.'.');
        }

        return $permission;
    }
}
