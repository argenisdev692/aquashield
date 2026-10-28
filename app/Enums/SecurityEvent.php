<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The security events that warrant a transactional email.
 *
 * One enum is the single source for the subject, heading and body of each
 * alert, so the queued job and the Blade view cannot drift. Events that do not
 * need an email (routine login, failed attempt) are audit-only and stay in
 * AuthEventSubscriber.
 */
enum SecurityEvent: string
{
    case PasswordChanged = 'password.changed';
    case TwoFactorEnabled = '2fa.enabled';
    case TwoFactorDisabled = '2fa.disabled';
    case RecoveryCodesRegenerated = '2fa.recovery_codes_regenerated';

    public function subject(): string
    {
        return match ($this) {
            self::PasswordChanged => 'Your AquaShield password was changed',
            self::TwoFactorEnabled => 'Two-factor authentication is now on',
            self::TwoFactorDisabled => 'Two-factor authentication was turned off',
            self::RecoveryCodesRegenerated => 'New two-factor recovery codes were generated',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::PasswordChanged => 'Password changed',
            self::TwoFactorEnabled => 'Two-factor authentication enabled',
            self::TwoFactorDisabled => 'Two-factor authentication disabled',
            self::RecoveryCodesRegenerated => 'Recovery codes regenerated',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::PasswordChanged => 'The password for your AquaShield account was changed. If this was you, no action is needed.',
            self::TwoFactorEnabled => 'An authenticator app is now required when you sign in. Keep your recovery codes somewhere safe.',
            self::TwoFactorDisabled => 'Your account now signs in with its password only. If you did not make this change, contact support immediately.',
            self::RecoveryCodesRegenerated => 'The recovery codes for your account were replaced. Older codes no longer work.',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::TwoFactorDisabled => 'warning',
            default => 'info',
        };
    }
}
