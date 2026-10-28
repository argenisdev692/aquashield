<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Shared\Domain\Ports\AuditPort;

final readonly class DeleteUserAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(User $target, User $actor): void
    {
        DB::transaction(function () use ($target, $actor): void {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('roles:id,name');

            Gate::forUser($actor)->authorize('delete', $locked);

            $locked->delete();

            $this->audit->log('user.deleted', $locked, [], $actor, 'users');
        });
    }
}
