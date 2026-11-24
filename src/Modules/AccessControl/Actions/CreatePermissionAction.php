<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\PermissionInputData;
use RuntimeException;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a custom permission (`is_system = false`). The request can never
 * forge the system flag.
 */
final readonly class CreatePermissionAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(PermissionInputData $data, User $actor): Permission
    {
        return DB::transaction(function () use ($data, $actor): Permission {
            try {
                $created = Permission::create([
                    'name' => $data->name,
                    'guard_name' => 'web',
                    'is_system' => false,
                ]);
            } catch (PermissionAlreadyExists) {
                throw ValidationException::withMessages([
                    'name' => 'A permission with this name already exists.',
                ]);
            }

            if (! $created instanceof Permission) {
                throw new RuntimeException('The configured permission model did not return a Spatie permission.');
            }

            $permission = $created;

            $this->registrar->forgetCachedPermissions();

            $this->audit->log('permission.created', $permission, [
                'name' => $permission->name,
            ], $actor, 'access-control');

            $permission->loadCount(['roles', 'users']);

            return $permission;
        });
    }
}
