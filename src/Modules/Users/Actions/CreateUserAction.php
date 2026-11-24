<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Users\Data\CreateUserData;
use Modules\Users\Enums\UserStatus;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Support\AccessCeiling;
use Shared\Domain\Ports\AuditPort;

/**
 * Creates an administrator-managed account.
 *
 * Two onboarding paths, one aggregate:
 *
 *  - Invitation (default): no usable credential, unverified e-mail, status
 *    `invited`; a queued signed link lets the owner choose the password.
 *  - Manual temporary password: the administrator sets a credential that must
 *    be replaced on first sign-in (`must_change_password`).
 *
 * The administrator never learns the random invitation credential, and the
 * account is never e-mail-verified on the owner's behalf in the invite path.
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
            $invited = $data->sendInvitation;
            $now = now();

            $user = new User;
            $user->fill([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'username' => $data->username,
                'email' => $data->email,
                'phone' => $data->phone,
                'date_of_birth' => $data->dateOfBirth,
                'address' => $data->address,
                'address_2' => $data->address2,
                'zip_code' => $data->zipCode,
                'city' => $data->city,
                'state' => $data->state,
                'country' => $data->country,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
            ]);

            $user->forceFill([
                'status' => $invited ? UserStatus::Invited : UserStatus::Active,
                'email_verified_at' => $invited ? null : $now,
                'invitation_sent_at' => $invited ? $now : null,
                'activated_at' => $invited ? null : $now,
                'must_change_password' => ! $invited,
            ]);

            // An invitation stores a random, unknown credential; the owner
            // replaces it through the signed link. Never a plaintext default.
            $user->password = $invited
                ? Str::password(64)
                : (string) $data->temporaryPassword;

            $user->registered_by_id = $actor->getKey();

            try {
                $user->save();
            } catch (UniqueConstraintViolationException) {
                // The FormRequest's unique rule is a TOCTOU check; the DB
                // constraint is still the authority. Surface the race as a
                // validation error, never as a 500.
                throw ValidationException::withMessages([
                    'email' => 'That email or username is already in use.',
                ]);
            }

            if ($data->roles !== []) {
                $user->assignRole($data->roles);
            }

            if ($data->permissions !== []) {
                $user->givePermissionTo($data->permissions);
            }

            $this->audit->log('user.created', $user, [
                'roles' => $data->roles,
                'permissions' => $data->permissions,
                'onboarding' => $invited ? 'invitation' : 'temporary_password',
            ], $actor, 'users');

            return $user;
        });

        // Never dispatch inside the transaction: a rollback must not send an
        // invitation for an account that no longer exists. The timestamp is
        // the single-use marker the worker will compare against.
        if ($data->sendInvitation) {
            SendUserInvitationJob::dispatch(
                $user,
                (int) $user->invitation_sent_at?->getTimestamp(),
            )->onQueue('emails')->afterCommit();
        }

        return $user->load(['roles:id,name', 'permissions:id,name']);
    }
}
