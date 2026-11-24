<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A half-open instant interval `[start, end)`. This is the only overlap rule in
 * the module: two appointments that merely touch (10:00–10:30 and 10:30–11:00)
 * do not conflict.
 */
final readonly class TimeInterval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('An interval must end after it starts.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function overlapsRange(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $this->start < $end && $this->end > $start;
    }
}
