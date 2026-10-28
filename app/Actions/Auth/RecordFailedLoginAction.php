<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Support\AuthRequestContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts one failed password attempt and applies the account lockout when the
 * configured threshold is reached.
 *
 * The counter is a shared write: two parallel requests must not both read the
 * same value and each write +1, or five attempts could take six failures. The
 * row is read `FOR UPDATE` inside one transaction; the email is queued after
 * the transaction commits so a queue outage cannot roll the lock back.
 */
final class RecordFailedLoginAction
{
    public function execute(User $user): void
    {
        $maxAttempts = max(1, (int) config('auth.hardening.lockout.max_attempts', 5));
        $lockoutMinutes = max(1, (int) config('auth.hardening.lockout.duration_minutes', 60));

        $result = DB::transaction(function () use ($user, $maxAttempts, $lockoutMinutes): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedUser instanceof User) {
                return ['attempts' => 0, 'locked' => false];
            }

            // A concurrent request may have locked the account first. Do not
            // count or notify again while the deadline is still in the future.
            if ($lockedUser->locked_until !== null && $lockedUser->locked_until->isFuture()) {
                return ['attempts' => (int) $lockedUser->failed_login_attempts, 'locked' => false];
            }

            $attempts = (int) $lockedUser->failed_login_attempts + 1;

            if ($attempts >= $maxAttempts) {
                $lockedUser->forceFill([
                    'failed_login_attempts' => 0,
                    'locked_until' => now()->addMinutes($lockoutMinutes),
                ])->save();

                return ['attempts' => $attempts, 'locked' => true];
            }

            $lockedUser->forceFill([
                'failed_login_attempts' => $attempts,
                'locked_until' => null,
            ])->save();

            return ['attempts' => $attempts, 'locked' => false];
        });

        $user->refresh();

        if ($result['locked'] !== true) {
            return;
        }

        try {
            SendSecurityAlert::forUser($user, SecurityEvent::AccountLocked, [
                ...AuthRequestContext::properties(),
                'failed_attempts' => (string) $result['attempts'],
                'locked_until' => $user->locked_until?->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            // The lock is already persisted; a queue outage must not turn the
            // failed login into a 500.
            report($exception);
        }
    }
}
