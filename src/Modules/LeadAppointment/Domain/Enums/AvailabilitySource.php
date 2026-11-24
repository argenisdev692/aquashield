<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

enum AvailabilitySource: string
{
    case Manual = 'manual';
    case Holiday = 'holiday';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
