<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Users\Enums\UserStatus;
use Shared\Domain\Ports\AuditPort;

/**
 * Consumes an invitation and turns the account on.
 *
 * The invitation is single-use by construction: `invitation_sent_at` must equal
 * the timestamp embedded in the signed URL, and it is cleared in the same
 * transaction that sets the password. A reused link therefore fails even while
 * its signature is still inside the 48-hour window.
 */
final readonly class ActivateUserAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(User $target, string $password, int $invitationTimestamp): User
    {
        return DB::transaction(function () use ($target, $password, $invitationTimestamp): User {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isInvited()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation has already been used. Sign in instead.',
                ]);
            }

            if ($locked->invitation_sent_at === null
                || $locked->invitation_sent_at->getTimestamp() !== $invitationTimestamp) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is no longer valid. Ask an administrator to send a new one.',
                ]);
            }

            $locked->forceFill([
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
                'activated_at' => now(),
                'suspended_at' => null,
                'invitation_sent_at' => null,
                'must_change_password' => false,
            ])->save();

            // A fresh account should not inherit a token or session from a
            // previous lifecycle; both are revoked before the new session is
            // established by the controller.
            $locked->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $locked->getKey())
                    ->delete();
            }

            $this->audit->log('user.activated', $locked, [], $locked, 'users');

            return $locked;
        });
    }
}
