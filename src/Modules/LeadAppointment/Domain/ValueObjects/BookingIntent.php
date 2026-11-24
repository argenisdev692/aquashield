<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Everything BookingGuard needs to decide one booking, with no repository or
 * framework in sight. One crew means the whole company shares the day, so the
 * intent carries the travel buffer and the daily cap.
 */
final readonly class BookingIntent
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $travelBufferMinutes,
        public int $dailyCapacity,
        public bool $isEmergency,
    ) {}
}
