<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Users\Enums\UserStatus;

/**
 * The one statement of "may this account authenticate?".
 *
 * Every sign-in transport asks it: Fortify's password path, passkeys, the
 * passwordless e-mail code, the TOTP compatibility route and the Sanctum token
 * endpoint. Keeping the copy here prevents the five transports from drifting —
 * a suspended account that can still mint a token is not suspended.
 */
final class AccountAccess
{
    /**
     * @throws ValidationException
     */
    public static function assertCanSignIn(User $user): void
    {
        if ($user->status->canSignIn()) {
            return;
        }

        $message = match ($user->status) {
            UserStatus::Invited => 'This account has not been activated yet. Use the link in your invitation email, or ask an administrator to resend it.',
            UserStatus::Suspended => 'This account is suspended. Contact an administrator to restore access.',
            UserStatus::Active => null,
        };

        throw ValidationException::withMessages([
            'email' => (string) $message,
        ]);
    }

    public static function messageFor(User $user): ?string
    {
        return match ($user->status) {
            UserStatus::Invited => 'This account has not been activated yet. Check your invitation email, or ask an administrator to resend it.',
            UserStatus::Suspended => 'This account is suspended. Contact an administrator to restore access.',
            UserStatus::Active => null,
        };
    }
}
