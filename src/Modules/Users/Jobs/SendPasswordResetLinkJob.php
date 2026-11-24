<?php

declare(strict_types=1);

namespace Modules\Users\Jobs;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

/**
 * Sends the branded password-set link to an already-active account.
 *
 * Kept separate from the invitation job: an active account recovering access
 * uses the Fortify broker (single-use, 60-minute TTL), while an invited account
 * uses the signed activation link. One notification does not serve both flows
 * because the messages and the TTLs are different promises.
 *
 * A double-click or redelivery for the same account is deduplicated for one
 * minute; a legitimate new reset after that creates a fresh broker token.
 */
final class SendPasswordResetLinkJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
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

        if (! $user instanceof User || $user->trashed() || ! $user->isActive()) {
            return;
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        $user->notify(new ResetPasswordNotification($token));
    }
}
