<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Auth\RecordFailedLoginAction;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reads the durable account-lockout state written by
 * {@see RecordFailedLoginAction}.
 *
 * The check is deliberately time-based and not "attempts >= max": a lock is a
 * deadline, so a request at minute 61 succeeds without any scheduled job
 * having to unlock the row first.
 */
final class AccountLockout
{
    public static function active(User $user): bool
    {
        return $user->locked_until instanceof Carbon && $user->locked_until->isFuture();
    }

    public static function remainingSeconds(User $user): int
    {
        if (! self::active($user)) {
            return 0;
        }

        /** @var Carbon $lockedUntil */
        $lockedUntil = $user->locked_until;

        return max(0, $lockedUntil->getTimestamp() - now()->getTimestamp());
    }

    public static function remainingMinutes(User $user): int
    {
        return max(1, (int) ceil(self::remainingSeconds($user) / 60));
    }

    public static function message(User $user): string
    {
        return trans_choice(
            '{1} Too many failed sign-in attempts. Try again in :count minute.|[2,*] Too many failed sign-in attempts. Try again in :count minutes.',
            self::remainingMinutes($user),
        );
    }
}
