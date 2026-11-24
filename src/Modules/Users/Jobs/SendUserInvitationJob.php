<?php

declare(strict_types=1);

namespace Modules\Users\Jobs;

use App\Models\User;
use App\Notifications\UserInvitationNotification;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Modules\Users\Support\InvitationLink;

/**
 * Delivers the signed activation link for an administrator-created account.
 *
 * This leaves the HTTP request: SMTP/API mail is external, and an outage must
 * not turn a successful account creation into a 500. The caller dispatches with
 * afterCommit(), so a rolled-back transaction never sends anything.
 *
 * The job carries the `invitation_sent_at` timestamp it was dispatched for.
 * `invitation_sent_at` is written by the create/resend action, never here — one
 * writer means the link the recipient receives is exactly the one the
 * activation endpoint compares. If a newer invitation was sent while this job
 * was queued, the job returns without mail: the newer job owns the link.
 *
 * Resilience: the transport carries the HTTP timeout and circuit breaker; this
 * job adds queue-level rate limiting, uniqueness and retry bounds.
 */
final class SendUserInvitationJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Rate-limited releases must not consume the exception budget.
     */
    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 3600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public User $user,
        public int $invitationTimestamp,
    ) {}

    /**
     * The provider rate limit is enforced per recipient and globally; the
     * limiter itself is registered in UsersServiceProvider.
     *
     * @return array<int, RateLimited>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }

    /**
     * A resend changes the timestamp, so it is a different job; a double-click
     * or redelivery for the same marker is deduplicated.
     */
    public function uniqueId(): string
    {
        return $this->user->uuid.':'.$this->invitationTimestamp;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(): void
    {
        $user = $this->user->fresh();

        // The account may have been activated, suspended or deleted between
        // the dispatch and the worker. Sending in any of those states would
        // hand out a link the activation endpoint must reject.
        if (! $user instanceof User || $user->trashed() || ! $user->isInvited() || $user->invitation_sent_at === null) {
            return;
        }

        if ($user->invitation_sent_at->getTimestamp() !== $this->invitationTimestamp) {
            return;
        }

        $sentAt = $user->invitation_sent_at;

        $url = URL::temporarySignedRoute(
            'users.activation.show',
            $sentAt->copy()->addHours(InvitationLink::EXPIRES_IN_HOURS),
            [
                'user' => $user->uuid,
                'invitation' => $this->invitationTimestamp,
            ],
        );

        $user->notify(new UserInvitationNotification(
            url: $url,
            recipientName: $user->name,
            expiresInHours: InvitationLink::EXPIRES_IN_HOURS,
        ));
    }
}
