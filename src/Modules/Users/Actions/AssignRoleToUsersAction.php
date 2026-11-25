<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Data\BulkActionResultData;
use Modules\Users\Data\BulkSkipData;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Enums\Role as RoleName;
use Shared\Domain\Ports\AuditPort;

/**
 * Bulk role change (sync, not add).
 *
 * The last ACTIVE super administrator cannot be demoted, and an actor cannot
 * change their own role. Both protections are reported as skip reasons; every
 * other row is committed in the same transaction.
 */
final readonly class AssignRoleToUsersAction
{
    public function __construct(
        private AuditPort $audit,
        private AccessCeiling $ceiling,
    ) {}

    /**
     * @param list<string> $uuids
     */
    public function execute(User $actor, array $uuids, string $role): BulkActionResultData
    {
        if (! $this->ceiling->isSuperAdmin($actor)) {
            $this->ceiling->assertCanGrantRoles($actor, [$role]);
        }

        return DB::transaction(function () use ($actor, $uuids, $role): BulkActionResultData {
            $users = User::query()
                ->with([
                    'roles:id,name',
                    'roles.permissions:id,name',
                    'permissions:id,name',
                ])
                ->whereIn('uuid', $uuids)
                ->lockForUpdate()
                ->get();

            if ($users->count() !== count($uuids)) {
                throw ValidationException::withMessages([
                    'uuids' => 'One or more users no longer exist.',
                ]);
            }

            $updated = 0;
            $skipped = [];

            foreach ($users as $user) {
                $reason = $this->skipReason($actor, $user, $role);

                if ($reason !== null) {
                    $skipped[] = new BulkSkipData((string) $user->uuid, $reason);

                    continue;
                }

                $user->syncRoles([$role]);

                $this->audit->log('user.roles.assigned', $user, [
                    'roles' => [$role],
                    'bulk' => true,
                ], $actor, 'users');

                $updated++;
            }

            return new BulkActionResultData($updated, $skipped);
        }, attempts: 3);
    }

    private function skipReason(User $actor, User $user, string $role): ?string
    {
        if ($actor->is($user)) {
            return 'You cannot change your own role.';
        }

        if ($this->ceiling->isLastActiveSuperAdmin($user)
            && $role !== RoleName::SuperAdmin->value) {
            return 'The last active super administrator cannot lose the SUPER_ADMIN role.';
        }

        if (! Gate::forUser($actor)->allows('assignRoles', $user)) {
            return 'You are not allowed to change this account.';
        }

        return null;
    }
}
