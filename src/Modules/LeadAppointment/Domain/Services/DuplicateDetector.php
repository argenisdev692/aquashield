<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\ValueObjects\DuplicateCandidate;
use Modules\LeadAppointment\Domain\ValueObjects\DuplicateMatch;
use Modules\LeadAppointment\Domain\ValueObjects\PhoneNumber;

/**
 * A repeat submission is linked, never rejected. During storm season a household
 * legitimately submits twice; a 422 would lose the second contact and annoy the
 * customer. Matching is deliberately conservative: email, phone, or full name +
 * property address, all inside the configured window.
 */
final readonly class DuplicateDetector
{
    /**
     * @param list<DuplicateCandidate> $candidates
     */
    public function detect(
        ?string $email,
        ?string $phone,
        string $firstName,
        string $lastName,
        ?string $address,
        array $candidates,
        int $windowDays,
        ?CarbonImmutable $now = null,
    ): ?DuplicateMatch {
        $now ??= CarbonImmutable::now();
        $cutoff = $now->subDays($windowDays);

        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPhone = PhoneNumber::fromString($phone)?->value;
        $normalizedName = $this->normalizeText($firstName.' '.$lastName);
        $normalizedAddress = $this->normalizeText($address);

        foreach ($candidates as $candidate) {
            if ($candidate->createdAt->lessThan($cutoff)) {
                continue;
            }

            if ($normalizedEmail !== null && $this->normalizeEmail($candidate->email) === $normalizedEmail) {
                return new DuplicateMatch($candidate->uuid, 'email');
            }

            if ($normalizedPhone !== null && PhoneNumber::fromString($candidate->phone)?->value === $normalizedPhone) {
                return new DuplicateMatch($candidate->uuid, 'phone');
            }

            if ($normalizedName !== ''
                && $normalizedAddress !== null
                && $this->normalizeText($candidate->fullName()) === $normalizedName
                && $this->normalizeText($candidate->address) === $normalizedAddress) {
                return new DuplicateMatch($candidate->uuid, 'name_address');
            }
        }

        return null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $value)));
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return $normalized === '' ? null : $normalized;
    }
}
