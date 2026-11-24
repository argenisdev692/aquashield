<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class TechnicianRegionMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The technician does not cover the lead region.');
    }
}
