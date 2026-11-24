<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class InvalidStatusTransitionException extends RuntimeException
{
    public static function for(string $from, string $to): self
    {
        return new self("Cannot move from [{$from}] to [{$to}].");
    }
}
