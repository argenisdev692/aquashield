<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class LeadHasAppointmentsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The lead has appointments and cannot be deleted or merged.');
    }
}
