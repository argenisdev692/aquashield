<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Application\Ports\CalendarSyncPort;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;

final readonly class SyncAppointmentToGoogleCalendar implements ShouldQueue
{
    public function __construct(
        private AppointmentRepository $appointments,
        private CalendarSyncPort $calendar,
    ) {}

    public function handle(AppointmentScheduled|AppointmentRescheduled|AppointmentCancelled $event): void
    {
        $appointment = $this->appointments->find($event->appointmentUuid);

        if ($appointment === null) {
            return;
        }

        if ($event instanceof AppointmentCancelled) {
            $this->calendar->delete($appointment);

            return;
        }

        if ($event instanceof AppointmentRescheduled) {
            $this->calendar->update($appointment);

            return;
        }

        $eventId = $this->calendar->create($appointment);

        if ($eventId !== null) {
            $this->appointments->setGoogleEventId($appointment->uuid, $eventId);
        }
    }
}
