<?php

declare(strict_types=1);

namespace Modules\Users\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends the verification link after an administrator changes a user's email.
 *
 * The update action clears `email_verified_at`; until this job delivers and the
 * recipient confirms, the new address is not trusted. The call is queued so an
 * SMTP outage cannot fail the profile save.
 */
final class SendEmailVerificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $user) {}

    public function handle(): void
    {
        $this->user->sendEmailVerificationNotification();
    }
}
