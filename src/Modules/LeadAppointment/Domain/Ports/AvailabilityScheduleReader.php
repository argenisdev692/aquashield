<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Ports;

use Modules\LeadAppointment\Domain\ValueObjects\AvailabilityException;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

/**
 * The read seam the availability engine needs. It speaks in Domain value
 * objects, never Eloquent models or Application Data. Availability is
 * company-wide until technician assignment returns with Claim + Scope Sheet.
 */
interface AvailabilityScheduleReader
{
    public function findActiveException(string $date): ?AvailabilityException;

    /**
     * @return array<string, AvailabilityException> keyed by Y-m-d
     */
    public function activeExceptionsBetween(string $from, string $to): array;

    /**
     * @return list<TimeWindow>
     */
    public function slotsForDay(int $dayOfWeek): array;

    /**
     * @return array<int, list<TimeWindow>> weekday => windows
     */
    public function slotsGroupedByDay(): array;
}
