<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Clears the brute-force counter after a successful credential check.
 *
 * A successful sign-in is proof the password is known again, so the
 * five-attempt budget starts over. Called by the web Login event subscriber
 * and by the token endpoint, which does not fire a Login event.
 */
final class ClearLoginFailuresAction
{
    public function execute(User $user): void
    {
        if ((int) $user->failed_login_attempts === 0 && $user->locked_until === null) {
            return;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->saveQuietly();
    }
}
