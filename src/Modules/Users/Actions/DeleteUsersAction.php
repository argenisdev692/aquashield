<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Support\AccessRevocation;
use Shared\Domain\Ports\AuditPort;

/**
 * All-or-nothing soft delete for one row or a bulk selection.
 *
 * One row above the actor's ceiling denies the whole request and rolls the
 * transaction back — a partial delete would leave the caller unable to tell
 * which rows succeeded. The policy is evaluated per row because the action is
 * authorised by permission, never by the UUID list.
 */
final readonly class DeleteUsersAction
{
    public function __construct(
        private AuditPort $audit,
        private AccessRevocation $revocation,
    ) {}

    /**
     * @param list<string> $uuids
     */
    public function execute(User $actor, array $uuids): void
    {
        DB::transaction(function () use ($actor, $uuids): void {
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

            foreach ($users as $user) {
                Gate::forUser($actor)->authorize('delete', $user);

                $user->delete();

                // A deleted account must not keep a live session or API token.
                $this->revocation->revoke($user);

                $this->audit->log('user.deleted', $user, [], $actor, 'users');
            }
        }, attempts: 3);
    }
}
