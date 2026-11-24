<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class CapacityExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The technician has reached the daily appointment capacity.');
    }
}
