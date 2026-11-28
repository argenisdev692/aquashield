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
        // The internal staff domain. The allowlist is used by admin create/
        // update and by the self-service profile screen, so omitting the
        // company domain makes every real staff account uneditable.
        'aquashieldrestorationusa.com',
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

        $domain = self::domainOf($value);

        if ($domain === null) {
            $fail('The :attribute must contain a valid domain.');

            return;
        }

        if (in_array($domain, self::blockedDomains(), true)) {
            $fail('The :attribute uses a temporary or blocked domain. Use a real email provider.');

            return;
        }

        if (! in_array($domain, self::allowedDomains(), true)) {
            $fail('The :attribute domain is not allowed. Use the company domain or a known provider.');
        }
    }

    /**
     * Boolean form used by non-validator callers (Google provisioning).
     */
    public static function passes(string $email): bool
    {
        $domain = self::domainOf($email);

        if ($domain === null) {
            return false;
        }

        return ! in_array($domain, self::blockedDomains(), true)
            && in_array($domain, self::allowedDomains(), true);
    }

    private static function domainOf(string $email): ?string
    {
        $atPos = strrpos($email, '@');

        if ($atPos === false || $atPos === 0 || $atPos === strlen($email) - 1) {
            return null;
        }

        return strtolower(substr($email, $atPos + 1));
    }

    /**
     * @return list<string>
     */
    private static function allowedDomains(): array
    {
        return self::configuredDomains('allowed', self::ALLOWED);
    }

    /**
     * @return list<string>
     */
    private static function blockedDomains(): array
    {
        return self::configuredDomains('blocked', self::BLOCKED);
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    private static function configuredDomains(string $key, array $default): array
    {
        $configured = config('auth.email_domains.'.$key);

        if (! is_array($configured) || $configured === []) {
            return $default;
        }

        $domains = array_map(
            static fn (mixed $domain): string => is_string($domain) ? strtolower(trim($domain)) : '',
            $configured,
        );

        return array_values(array_unique(array_filter($domains, static fn (string $domain): bool => $domain !== '')));
    }
}
