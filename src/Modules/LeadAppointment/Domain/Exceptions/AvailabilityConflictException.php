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
}
