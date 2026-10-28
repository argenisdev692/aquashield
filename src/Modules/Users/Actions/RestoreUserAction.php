<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Ports\AuditPort;

final readonly class RestoreUserAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(User $target, User $actor): User
    {
        return DB::transaction(function () use ($target, $actor): User {
            $locked = User::withTrashed()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('roles:id,name');

            Gate::forUser($actor)->authorize('restore', $locked);

            if (! $locked->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'The user is not deleted.',
                ]);
            }

            $locked->restore();

            $this->audit->log('user.restored', $locked, [], $actor, 'users');

            return $locked->refresh()->load(['roles:id,name', 'permissions:id,name']);
        });
    }
}
