<?php

declare(strict_types=1);

namespace Modules\AccessControl\Actions;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Data\PermissionInputData;
use Shared\Domain\Enums\Permission as PermissionEnum;
use Shared\Domain\Ports\AuditPort;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renames a custom permission. The seeded catalogue is code-referenced and is
 * therefore read-only; assignments are by id, so a custom rename is safe.
 */
final readonly class UpdatePermissionAction
{
    public function __construct(
        private AuditPort $audit,
        private PermissionRegistrar $registrar,
    ) {}

    public function execute(Permission $permission, PermissionInputData $data, User $actor): Permission
    {
        return DB::transaction(function () use ($permission, $data, $actor): Permission {
            $locked = Permission::query()
                ->whereKey($permission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((bool) $locked->getAttribute('is_system')) {
                throw ValidationException::withMessages([
                    'name' => 'System permissions cannot be renamed.',
                ]);
            }

            $previousName = (string) $locked->name;

            if ($data->name !== $previousName && in_array($data->name, PermissionEnum::userValues(), true)) {
                throw ValidationException::withMessages([
                    'name' => 'This permission name is reserved by the application.',
                ]);
            }

            try {
                $locked->update(['name' => $data->name]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'A permission with this name already exists.',
                ]);
            }

            // Spatie's own flush runs before this transaction commits; repair
            // the registry after the commit so a concurrent read cannot keep a
            // pre-commit snapshot for the cache TTL.
            DB::afterCommit(fn () => $this->registrar->forgetCachedPermissions());

            $this->audit->log('permission.updated', $locked, [
                'name' => $locked->name,
                'previous_name' => $previousName === $locked->name ? null : $previousName,
            ], $actor, 'access-control');

            return $locked->loadCount(['roles', 'users']);
        });
    }
}
