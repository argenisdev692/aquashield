<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class PastDateSchedulingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An appointment cannot be scheduled in the past.');
    }
}
