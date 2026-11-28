<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

/**
 * The database enforces one active exception per date. A retry, a concurrent
 * bulk range or a restore can collide with that unique index; the caller gets
 * a business conflict (422), never a raw QueryException (500).
 */
final class AvailabilityConflictException extends RuntimeException
{
    public static function forDate(string $date): self
    {
        return new self('An availability exception already exists for '.$date.'.');
    }

    public static function forScope(): self
    {
        return new self('An availability exception already exists for this date.');
    }

    public static function forTotalRuleLimit(): self
    {
        return new self('The maximum number of active availability rules has been reached.');
    }

    public static function forWeekdayRuleLimit(): self
    {
        return new self('The maximum number of availability rules for this weekday has been reached.');
    }
}
