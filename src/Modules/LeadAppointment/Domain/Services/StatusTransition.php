<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;

final readonly class StatusTransition
{
    public function assertLead(LeadStatus $from, LeadStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransitionException::for($from->value, $to->value);
        }
    }

    public function assertAppointment(AppointmentStatus $from, AppointmentStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransitionException::for($from->value, $to->value);
        }
    }
}
