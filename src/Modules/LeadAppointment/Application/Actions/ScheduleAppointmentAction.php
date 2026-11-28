<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;

/**
 * The scheduling use case. The writer owns the company-calendar lock, the
 * transaction, availability resolution and the final booking guard.
 *
 * Technician assignment is deliberately absent: it belongs to the Claim +
 * Scope Sheet phase, where the assigned person is a `User` with the
 * TECHNICAL_SERVICES role — not a row in a separate technicians table.
 */
final readonly class ScheduleAppointmentAction
{
    public function __construct(
        private AppointmentWriter $appointmentWriter,
        private Dispatcher $events,
    ) {}

    public function execute(ScheduleAppointmentData $data, ?int $actorId = null): AppointmentData
    {
        $appointment = $this->appointmentWriter->createWithLock($data, $actorId);

        $this->events->dispatch(new AppointmentScheduled($appointment->uuid));

        return $appointment;
    }
}
