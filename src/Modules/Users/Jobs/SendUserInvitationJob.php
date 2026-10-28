<?php

declare(strict_types=1);

namespace Modules\Users\Jobs;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;

/**
 * Sends the account holder a password-set link after an administrator creates
 * their account or asks for a resend.
 *
 * This leaves the HTTP request: SMTP is an external call, and an outage must
 * not turn a successful account creation into a 500. The caller dispatches with
 * afterCommit(), so a rolled-back transaction never sends anything.
 *
 * The job creates the broker token directly instead of calling
 * `Password::sendResetLink()`, because the broker's 60-second throttle would
 * silently drop an immediate resend. The admin route still has its own burst
 * limit, and every request is audited; the public forgot-password flow keeps
 * the broker throttle.
 *
 * The job drops itself when the model is gone (`$deleteWhenMissingModels`) —
 * a deleted account must not be retried forever.
 */
final class SendUserInvitationJob implements ShouldQueue
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
        // The facade returns the concrete broker, but Intelephense cannot infer
        // that from `Password::broker()`. The annotation is the contract.
        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($this->user);

        $this->user->notify(new ResetPasswordNotification($token));
    }
}
