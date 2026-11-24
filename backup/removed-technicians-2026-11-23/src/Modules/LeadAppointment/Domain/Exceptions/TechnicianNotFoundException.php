<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class TechnicianNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Technician not found.');
    }
}
