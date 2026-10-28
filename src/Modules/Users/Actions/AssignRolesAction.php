<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Ports\AuditPort;

/**
 * Replaces a user's role set (sync, not add).
 *
 * The actor cannot change their own roles, cannot grant a role that carries a
 * permission they lack, and cannot strip SUPER_ADMIN from the last remaining
 * super administrator. The last rule is what keeps the installation
 * recoverable; the self rule keeps the actor from locking themselves out by
 * accident.
 */
final readonly class AssignRolesAction
{
    public function __construct(
        private AuditPort $audit,
        private AccessCeiling $ceiling,
    ) {}

    /**
     * @param list<string> $roles
     */
    public function execute(User $target, User $actor, array $roles): User
    {
        $roles = array_values(array_unique($roles));

        return DB::transaction(function () use ($target, $actor, $roles): User {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('roles:id,name');

            Gate::forUser($actor)->authorize('assignRoles', $locked);
            $this->ceiling->assertCanGrantRoles($actor, $roles);

            $demotesLastSuperAdmin = $this->ceiling->isSuperAdmin($locked)
                && ! in_array(AccessCeiling::SUPER_ADMIN, $roles, true)
                && $this->ceiling->isLastSuperAdmin($locked);

            if ($demotesLastSuperAdmin) {
                throw ValidationException::withMessages([
                    'roles' => 'The last super administrator cannot lose the SUPER_ADMIN role.',
                ]);
            }

            $locked->syncRoles($roles);

            $this->audit->log('user.roles.assigned', $locked, [
                'roles' => $roles,
            ], $actor, 'users');

            return $locked->refresh()->load(['roles:id,name', 'permissions:id,name']);
        });
    }
}
