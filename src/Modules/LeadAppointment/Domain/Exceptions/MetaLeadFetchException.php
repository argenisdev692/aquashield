<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Exceptions;

use RuntimeException;

final class MetaLeadFetchException extends RuntimeException
{
    public static function unavailable(string $reason): self
    {
        return new self('Meta lead could not be fetched: '.$reason);
    }
}
