<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Application\Ports\CalendarSyncPort;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Throwable;

final class SyncAppointmentToGoogleCalendar implements ShouldQueue
{
    public int $tries = 3;

    /**
     * The Google client does not expose its HTTP timeout through the Spatie
     * package; the worker bound is the only hard stop around an unresponsive
     * calendar call.
     */
    public int $timeout = 30;

    public int $backoff = 60;

    public function __construct(
        private readonly AppointmentReader $appointments,
        private readonly AppointmentWriter $appointmentWriter,
        private readonly CalendarSyncPort $calendar,
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
            $this->appointmentWriter->setGoogleEventId($appointment->uuid, $eventId);
        }
    }

    public function failed(Throwable $exception): void
    {
        // The calendar payload contains customer PII; the class is enough to
        // alert an operator without copying an address into the log.
        Log::warning('lead-appointment.google_calendar.sync_failed', [
            'listener' => self::class,
            'exception' => $exception::class,
        ]);
    }
}
