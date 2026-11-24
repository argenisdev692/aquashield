<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * A small candidate projection for duplicate detection. The repository selects
 * only these columns; the detector stays framework-free and unit-testable.
 */
final readonly class DuplicateCandidate
{
    public function __construct(
        public string $uuid,
        public ?string $email,
        public ?string $phone,
        public string $firstName,
        public string $lastName,
        public ?string $address,
        public CarbonImmutable $createdAt,
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }
}
