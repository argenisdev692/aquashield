<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Modules\LeadAppointment\Domain\ValueObjects\SpamAssessment;

/**
 * Email-quality scorer and hard-domain blocker. Placeholder and disposable
 * domains are rejected at the public boundary; role-based and gibberish local
 * parts are scored so a legitimate business inbox is reviewed, not silently
 * discarded. Framework-free: the provider supplies the lists.
 */
final readonly class EmailSpamGuard
{
    /**
     * @param list<string> $blockedDomains
     * @param list<string> $disposableDomains
     * @param list<string> $rolePrefixes
     */
    public function __construct(
        private array $blockedDomains,
        private array $disposableDomains,
        private array $rolePrefixes,
        private int $threshold,
    ) {}

    public function isBlocked(string $email): bool
    {
        $domain = $this->domain($email);

        if ($domain === null) {
            return true;
        }

        return $this->matchesDomainList($domain, $this->blockedDomains)
            || $this->isReservedTld($domain)
            || $this->matchesDomainList($domain, $this->disposableDomains);
    }

    public function assess(?string $email): SpamAssessment
    {
        if ($email === null || trim($email) === '') {
            return new SpamAssessment(score: 0, isSpam: false, reasons: []);
        }

        $domain = $this->domain($email);
        $local = $this->localPart($email);
        $score = 0;
        $reasons = [];

        if ($domain === null || $this->matchesDomainList($domain, $this->blockedDomains) || $this->isReservedTld($domain)) {
            return new SpamAssessment(
                score: 100,
                isSpam: true,
                reasons: ['email:blocked_domain'],
            );
        }

        if ($this->matchesDomainList($domain, $this->disposableDomains)) {
            $score += 10;
            $reasons[] = 'email:disposable_domain';
        }

        if (in_array($local, $this->rolePrefixes, true)) {
            $score += 3;
            $reasons[] = 'email:role_address';
        }

        if ($this->isGibberishLocalPart($local)) {
            $score += 2;
            $reasons[] = 'email:gibberish_local_part';
        }

        if ($this->localMatchesDomain($local, $domain)) {
            $score += 2;
            $reasons[] = 'email:local_matches_domain';
        }

        return new SpamAssessment(
            score: $score,
            isSpam: $score >= $this->threshold,
            reasons: array_values(array_unique($reasons)),
        );
    }

    private function domain(string $email): ?string
    {
        $at = mb_strrpos($email, '@');

        if ($at === false || $at === mb_strlen($email) - 1) {
            return null;
        }

        return mb_strtolower(trim(mb_substr($email, $at + 1)));
    }

    private function localPart(string $email): string
    {
        $at = mb_strrpos($email, '@');

        return mb_strtolower(trim($at === false ? $email : mb_substr($email, 0, $at)));
    }

    /**
     * @param list<string> $domains
     */
    private function matchesDomainList(string $domain, array $domains): bool
    {
        foreach ($domains as $blocked) {
            $blocked = mb_strtolower(trim((string) $blocked));

            if ($blocked === '') {
                continue;
            }

            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    private function isReservedTld(string $domain): bool
    {
        if (! str_contains($domain, '.')) {
            return true;
        }

        return preg_match('/\.(test|invalid|localhost|example)$/i', $domain) === 1;
    }

    private function isGibberishLocalPart(string $local): bool
    {
        if (mb_strlen($local) < 3 || mb_strlen($local) > 64) {
            return true;
        }

        if (ctype_digit($local)) {
            return true;
        }

        if (preg_match('/(.)\1{3,}/u', $local) === 1) {
            return true;
        }

        if (preg_match('/[bcdfghjklmnpqrstvwxz]{6,}/i', $local) === 1) {
            return true;
        }

        return preg_match('/(qwerty|asdf|zxcv|12345|qazwsx)/i', $local) === 1;
    }

    private function localMatchesDomain(string $local, string $domain): bool
    {
        $base = strstr($domain, '.', true);

        return $base !== false && $base !== '' && str_contains($local, $base);
    }
}
