<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Users\Data\CreateUserData;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Ports\AuditPort;

/**
 * Creates an admin-managed account.
 *
 * The account is created without a known password: a random one is stored and
 * the user receives a password-reset link, so the administrator never handles
 * a credential. Admin-created accounts are e-mail-verified by the creating
 * administrator — this is an internal staff workflow, and a verification wall
 * here would only lock the new user out of the very screens they were hired
 * for.
 */
final readonly class CreateUserAction
{
    public function __construct(
        private AuditPort $audit,
        private AccessCeiling $ceiling,
    ) {}

    public function execute(CreateUserData $data, User $actor): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        // Refuse before the first write: a rejected grant must not leave a
        // half-created account behind.
        $this->ceiling->assertCanGrantRoles($actor, $data->roles);
        $this->ceiling->assertCanGrantPermissions($actor, $data->permissions);

        $user = DB::transaction(function () use ($data, $actor): User {
            $user = new User;
            $user->fill([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'username' => $data->username,
                'email' => $data->email,
                'phone' => $data->phone,
                'date_of_birth' => $data->dateOfBirth,
                'city' => $data->city,
                'state' => $data->state,
                'country' => $data->country,
            ]);

            $user->password = Str::password(32);
            $user->email_verified_at = now();
            $user->registered_by_id = $actor->getKey();
            $user->save();

            if ($data->roles !== []) {
                $user->assignRole($data->roles);
            }

            if ($data->permissions !== []) {
                $user->givePermissionTo($data->permissions);
            }

            $this->audit->log('user.created', $user, [
                'roles' => $data->roles,
                'permissions' => $data->permissions,
            ], $actor, 'users');

            return $user;
        });

        // Never dispatch inside the transaction: a rollback must not send an
        // invitation for an account that no longer exists.
        SendUserInvitationJob::dispatch($user)->afterCommit();

        return $user->load(['roles:id,name', 'permissions:id,name']);
    }
}
