<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A local daily availability window (`H:i`–`H:i`). Multiple windows per day are
 * allowed (split shift / lunch break); the window is half-open on the right.
 */
final readonly class TimeWindow
{
    public function __construct(
        public string $start,
        public string $end,
    ) {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) !== 1
            || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) !== 1) {
            throw new InvalidArgumentException('A time window must use HH:MM.');
        }

        if ($start >= $end) {
            throw new InvalidArgumentException('A time window must end after it starts.');
        }
    }

    public function contains(string $time): bool
    {
        return $time >= $this->start && $time < $this->end;
    }

    public function lengthMinutes(): int
    {
        [$startHour, $startMinute] = array_map(intval(...), explode(':', $this->start));
        [$endHour, $endMinute] = array_map(intval(...), explode(':', $this->end));

        return (($endHour * 60) + $endMinute) - (($startHour * 60) + $startMinute);
    }
}
