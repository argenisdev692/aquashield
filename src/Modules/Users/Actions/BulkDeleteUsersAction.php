<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Ports\AuditPort;

/**
 * All-or-nothing bulk soft delete.
 *
 * One row above the actor's ceiling denies the whole request and rolls the
 * transaction back — a partial delete would leave the caller unable to tell
 * which rows succeeded. The policy is evaluated per row because the action is
 * authorised by permission, never by the UUID list.
 */
final readonly class BulkDeleteUsersAction
{
    public function __construct(private AuditPort $audit) {}

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

                $this->audit->log('user.deleted', $user, [], $actor, 'users');
            }
        });
    }
}
