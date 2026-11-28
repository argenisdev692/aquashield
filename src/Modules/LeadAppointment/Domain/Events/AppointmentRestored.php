<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class AppointmentRestored implements ShouldDispatchAfterCommit
{
    public function __construct(public string $appointmentUuid) {}
}
