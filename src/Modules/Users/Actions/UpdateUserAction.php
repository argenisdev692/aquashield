<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Users\Data\UpdateUserData;
use Shared\Domain\Ports\AuditPort;

/**
 * Updates profile fields only. Roles and direct permissions have their own
 * actions so an access change is never hidden inside a profile save.
 */
final readonly class UpdateUserAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(UpdateUserData $data, User $target, User $actor): User
    {
        return DB::transaction(function () use ($data, $target, $actor): User {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('roles:id,name');

            Gate::forUser($actor)->authorize('update', $locked);

            $locked->fill([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'username' => $data->username,
                'email' => $data->email,
                'phone' => $data->phone,
                'date_of_birth' => $data->dateOfBirth,
                'city' => $data->city,
                'state' => $data->state,
                'country' => $data->country,
            ])->save();

            $changed = array_values(array_diff(
                array_keys($locked->getChanges()),
                ['updated_at'],
            ));

            $this->audit->log('user.updated', $locked, [
                'changed' => $changed,
            ], $actor, 'users');

            return $locked->load(['roles:id,name', 'permissions:id,name']);
        });
    }
}
