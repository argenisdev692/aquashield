<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class SlotUnavailableException extends RuntimeException
{
    public static function outsideWindow(): self
    {
        return new self('The requested time is outside the availability window.');
    }

    public static function overlap(): self
    {
        return new self('The lead already has an appointment overlapping this time.');
    }

    public static function closed(string $reason): self
    {
        return new self('The requested day is closed: '.$reason.'.');
    }
}
