<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

/**
 * A restored lead would collide with the partial unique index on
 * (external_source, external_id). The database correctly prevents two active
 * leads for the same provider event; the restore path must translate that into
 * a business conflict instead of a 500.
 */
final class LeadRestoreConflictException extends RuntimeException
{
    public static function forExternalId(): self
    {
        return new self('Another active lead already uses this external source and id; restore the other lead or merge them.');
    }

    public static function forBulk(): self
    {
        return new self('One of the selected leads would duplicate an active external lead; the restore was rolled back.');
    }
}
