<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Ports\AuditPort;

/**
 * All-or-nothing restore for one row or a bulk selection. Only rows that are
 * actually trashed are accepted, so a stale selection cannot silently become a
 * no-op.
 */
final readonly class RestoreUsersAction
{
    public function __construct(private AuditPort $audit) {}

    /**
     * @param list<string> $uuids
     * @return Collection<int, User>
     */
    public function execute(User $actor, array $uuids): Collection
    {
        return DB::transaction(function () use ($actor, $uuids): Collection {
            $users = User::onlyTrashed()
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
                    'uuids' => 'One or more users are not deleted.',
                ]);
            }

            foreach ($users as $user) {
                Gate::forUser($actor)->authorize('restore', $user);

                $user->restore();

                $this->audit->log('user.restored', $user, [], $actor, 'users');
            }

            // `restore()` already clears `deleted_at` in memory and the roles /
            // permissions were eager-loaded above — refreshing each row would be
            // three extra queries per restored account for no new state.
            return $users;
        }, attempts: 3);
    }
}
