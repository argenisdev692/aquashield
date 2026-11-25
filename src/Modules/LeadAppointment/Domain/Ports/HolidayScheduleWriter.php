<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Ports;

/**
 * The write seam for holiday materialisation.
 *
 * A sync is one atomic operation: the purge and the inserts must not be
 * observable half-applied. The Domain service supplies dates and names; the
 * adapter owns the transaction.
 */
interface HolidayScheduleWriter
{
    /**
     * @param array<string, string> $holidays date (Y-m-d) => holiday name
     * @return array{purged: int, created: int, skipped: int}
     */
    public function syncHolidays(array $holidays, ?string $purgeFrom = null): array;
}
