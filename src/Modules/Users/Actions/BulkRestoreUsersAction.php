<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Ports\AuditPort;

/**
 * All-or-nothing bulk restore. Only rows that are actually trashed are
 * accepted, so a stale selection cannot silently become a no-op.
 */
final readonly class BulkRestoreUsersAction
{
    public function __construct(private AuditPort $audit) {}

    /**
     * @param list<string> $uuids
     */
    public function execute(User $actor, array $uuids): void
    {
        DB::transaction(function () use ($actor, $uuids): void {
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
        });
    }
}
