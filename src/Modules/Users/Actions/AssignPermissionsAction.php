<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Ports\AuditPort;

/**
 * Replaces a user's DIRECT permissions.
 *
 * Role-derived permissions are untouched: syncPermissions() only manages the
 * `model_has_permissions` rows. That is exactly the requested behaviour —
 * a MANAGER keeps what the role grants and gains (or loses) only the explicit
 * overrides on top.
 */
final readonly class AssignPermissionsAction
{
    public function __construct(
        private AuditPort $audit,
        private AccessCeiling $ceiling,
    ) {}

    /**
     * @param list<string> $permissions
     */
    public function execute(User $target, User $actor, array $permissions): User
    {
        $permissions = array_values(array_unique($permissions));

        return DB::transaction(function () use ($target, $actor, $permissions): User {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('roles:id,name');

            Gate::forUser($actor)->authorize('assignPermissions', $locked);
            $this->ceiling->assertCanGrantPermissions($actor, $permissions);

            $locked->syncPermissions($permissions);

            $this->audit->log('user.permissions.assigned', $locked, [
                'permissions' => $permissions,
            ], $actor, 'users');

            return $locked->refresh()->load(['roles:id,name', 'permissions:id,name']);
        });
    }
}
