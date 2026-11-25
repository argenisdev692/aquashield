<?php

declare(strict_types=1);

namespace Modules\Users\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends the verification link after an administrator changes a user's email.
 *
 * The update action clears `email_verified_at`; until this job delivers and the
 * recipient confirms, the new address is not trusted. The call is queued so an
 * SMTP outage cannot fail the profile save. A double dispatch for the same
 * account is deduplicated for one minute.
 */
final class SendEmailVerificationJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $user) {}

    public function uniqueId(): string
    {
        return $this->user->uuid;
    }

    public function handle(): void
    {
        $user = $this->user->fresh();

        // `fresh()` bypasses the soft-delete scope, so a deleted account still
        // returns here. A suspended account has no usable session either: the
        // link would only verify an address on an account that cannot sign in.
        if (! $user instanceof User || $user->trashed() || ! $user->isActive()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
