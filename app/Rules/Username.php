<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The canonical username contract.
 *
 * Usernames are an optional display handle, never a login credential; email
 * remains the Fortify identifier. The rule is deliberately ASCII-only and
 * lower-case: the write actions normalize with Str::lower() before validation,
 * which also makes the unique index effectively case-insensitive.
 */
final class Username implements ValidationRule
{
    public const int MIN = 3;

    public const int MAX = 20;

    /**
     * Starts and ends alphanumeric, allows single . _ - separators, rejects
     * consecutive separators, and therefore also rejects leading/trailing ones.
     */
    public const string PATTERN = '/^(?!.*[._-]{2})[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/';

    /**
     * Route names, infrastructure words and role names that would be
     * confusing in a URL, a mention or an audit row.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        'admin',
        'administrator',
        'abuse',
        'api',
        'aquashield',
        'billing',
        'company-data',
        'contact',
        'cookie-consent',
        'dashboard',
        'email',
        'guest',
        'help',
        'hr',
        'info',
        'login',
        'logout',
        'marketing',
        'me',
        'moderator',
        'no-reply',
        'noreply',
        'null',
        'otp',
        'password',
        'postmaster',
        'private',
        'profile',
        'public',
        'register',
        'root',
        'sales',
        'security',
        'settings',
        'signin',
        'signup',
        'super-admin',
        'super_admin',
        'superadmin',
        'support',
        'system',
        'two-factor',
        'two-factor-challenge',
        'undefined',
        'user',
        'users',
        'verification',
        'verify',
        'webmaster',
        'www',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $length = strlen($value);

        if ($length < self::MIN || $length > self::MAX) {
            $fail('The :attribute must be between '.self::MIN.' and '.self::MAX.' characters.');

            return;
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            $fail('The :attribute must start and end with a letter or number and use only letters, numbers, dots, dashes or underscores.');

            return;
        }

        if (ctype_digit($value)) {
            $fail('The :attribute cannot contain only numbers.');

            return;
        }

        if (in_array($value, self::RESERVED, true)) {
            $fail('The :attribute is reserved. Choose another.');
        }
    }
}
