<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class LeadNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Lead not found.');
    }
}
