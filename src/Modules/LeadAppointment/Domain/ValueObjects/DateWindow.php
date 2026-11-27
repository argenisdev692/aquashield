<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * An inclusive instant range in the company timezone. The dashboard's period
 * selector resolves to one of these; readers convert to UTC at the query edge.
 */
final readonly class DateWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}
}
