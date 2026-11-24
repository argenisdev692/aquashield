<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

enum LeadActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Sms = 'sms';
    case Note = 'note';
    case Meeting = 'meeting';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
