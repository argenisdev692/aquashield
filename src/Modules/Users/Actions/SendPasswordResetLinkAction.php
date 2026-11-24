<?php

declare(strict_types=1);

namespace Modules\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Users\Jobs\SendPasswordResetLinkJob;
use Shared\Domain\Ports\AuditPort;

/**
 * Re-sends the password-set link for an already-active account.
 *
 * This is the recovery path, not the onboarding path. An invited account must
 * be re-invited (which rotates `invitation_sent_at`) so the two link types do
 * not become interchangeable.
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

        if (! $target->isActive()) {
            throw ValidationException::withMessages([
                'user' => 'Only an active account can receive a password-set link. Resend the invitation instead.',
            ]);
        }

        SendPasswordResetLinkJob::dispatch($target)->onQueue('emails')->afterCommit();

        $this->audit->log('user.password_reset_link_sent', $target, [], $actor, 'users');
    }
}
