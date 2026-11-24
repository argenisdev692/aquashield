<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Jobs\SendUserInvitationJob;
use Shared\Domain\Ports\AuditPort;

/**
 * Rotates the signed invitation for an invited account.
 *
 * `invitation_sent_at` is rewritten before dispatch. Every older signed URL
 * still has a valid signature, but its embedded timestamp no longer equals the
 * column, so the activation endpoint rejects it. That is the invalidation the
 * brief asks for, without a second token table.
 */
final readonly class ResendInvitationAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(User $target, User $actor): User
    {
        $updated = DB::transaction(function () use ($target, $actor): User {
            $locked = User::query()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('resendInvitation', $locked);

            if ($locked->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'A deleted account cannot be invited. Restore it first.',
                ]);
            }

            if (! $locked->isInvited()) {
                throw ValidationException::withMessages([
                    'user' => 'Only an invited account can receive an invitation. Use the password reset for an active account.',
                ]);
            }

            $locked->forceFill(['invitation_sent_at' => now()])->save();

            $this->audit->log('user.invitation_resent', $locked, [], $actor, 'users');

            return $locked;
        });

        SendUserInvitationJob::dispatch(
            $updated,
            (int) $updated->invitation_sent_at?->getTimestamp(),
        )->onQueue('emails')->afterCommit();

        return $updated;
    }
}
