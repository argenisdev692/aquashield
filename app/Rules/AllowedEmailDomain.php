<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Anti-spam email domain filter (robust, ORM-safe, no N+1).
 *
 * Only well-known consumer / business email domains are accepted.
 * Disposable / temporary domains are explicitly blocked.
 */
final class AllowedEmailDomain implements ValidationRule
{
    /**
     * @var list<string>
     */
    public const array ALLOWED = [
        'gmail.com',
        'googlemail.com',
        'outlook.com',
        'outlook.es',
        'hotmail.com',
        'live.com',
        'msn.com',
        'proton.me',
        'protonmail.com',
        'protonmail.ch',
        'yahoo.com',
        'yahoo.es',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'fastmail.com',
        'zoho.com',
        'zoho.eu',
        'company.com',
    ];

    /**
     * @var list<string>
     */
    public const array BLOCKED = [
        'mailinator.com',
        '10minutemail.com',
        'guerrillamail.com',
        'maildrop.cc',
        'mailnesia.com',
        'yopmail.com',
        'tempmail.net',
        'disposable.com',
        'throwawaymail.com',
        'fakeinbox.com',
        'sharklasers.com',
        'spam4.me',
        'getairmail.com',
        'mailnesia.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        // Extract domain from email (no regex overhead, clean split)
        $atPos = strrpos($value, '@');

        if ($atPos === false || $atPos === 0 || $atPos === strlen($value) - 1) {
            $fail('The :attribute must contain a valid domain.');

            return;
        }

        $domain = strtolower(substr($value, $atPos + 1));

        if (in_array($domain, self::BLOCKED, true)) {
            $fail('The :attribute uses a temporary or blocked domain. Use a real email provider.');

            return;
        }

        if (! in_array($domain, self::ALLOWED, true)) {
            $fail('The :attribute domain is not allowed. Use a known provider (gmail, outlook, proton, yahoo, etc.).');
        }
    }
}
