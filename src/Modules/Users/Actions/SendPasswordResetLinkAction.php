<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Jobs\SendUserInvitationJob;
use Shared\Domain\Ports\AuditPort;

/**
 * Re-sends the password-set link for an existing account.
 *
 * The administrator never sees or chooses a password. This action only asks the
 * queue to send the same single-use link the create flow uses, and records the
 * request in the audit trail. The row-level ceiling still applies: an actor
 * cannot trigger a reset for an account above their own access.
 */
final readonly class SendPasswordResetLinkAction
{
    public function __construct(private AuditPort $audit) {}

    public function execute(User $target, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $target);

        if ($target->trashed()) {
            throw ValidationException::withMessages([
                'user' => 'A deleted account cannot receive a password-set link. Restore it first.',
            ]);
        }

        SendUserInvitationJob::dispatch($target)->afterCommit();

        $this->audit->log('user.password_reset_link_sent', $target, [], $actor, 'users');
    }
}
