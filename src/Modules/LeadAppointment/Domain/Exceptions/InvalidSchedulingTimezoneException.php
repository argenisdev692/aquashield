<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use Modules\LeadAppointment\Domain\Enums\Region;
use RuntimeException;

final class InvalidSchedulingTimezoneException extends RuntimeException
{
    public static function for(Region $region, string $timezone): self
    {
        return new self("The timezone [{$timezone}] is not valid for the [{$region->value}] region.");
    }
}
