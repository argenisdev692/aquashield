<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;

/**
 * Domain projection of one date-specific override. `source=holiday` rows are
 * system-managed and may be rebuilt; `manual` rows always win and are never
 * purged by a holiday resync.
 */
final readonly class AvailabilityException
{
    public function __construct(
        public string $date,
        public bool $isAvailable,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $reason,
        public AvailabilitySource $source,
    ) {}

    public static function closed(string $date, string $reason, AvailabilitySource $source): self
    {
        return new self($date, false, null, null, $reason, $source);
    }

    public static function open(string $date, string $startTime, string $endTime, ?string $reason): self
    {
        return new self($date, true, $startTime, $endTime, $reason, AvailabilitySource::Manual);
    }
}
