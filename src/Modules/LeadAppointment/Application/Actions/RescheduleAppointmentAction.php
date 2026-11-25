<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\RescheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\Services\BookingGuard;
use Modules\LeadAppointment\Domain\ValueObjects\BookingIntent;

final readonly class RescheduleAppointmentAction
{
    public function __construct(
        private AppointmentReader $appointments,
        private AppointmentWriter $appointmentWriter,
        private AvailabilityResolver $availability,
        private BookingGuard $guard,
        private Dispatcher $events,
    ) {}

    public function execute(string $uuid, RescheduleAppointmentData $data): AppointmentData
    {
        $appointment = $this->appointments->find($uuid);

        if ($appointment === null) {
            throw new AppointmentNotFoundException;
        }

        $status = AppointmentStatus::from($appointment->status);

        if (! in_array($status, [AppointmentStatus::Requested, AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
            throw InvalidStatusTransitionException::for($status->value, 'rescheduled');
        }

        $currentDuration = (int) CarbonImmutable::parse($appointment->startsAt)
            ->diffInMinutes(CarbonImmutable::parse($appointment->endsAt));
        $duration = $data->durationMinutes ?? ($currentDuration > 0 ? $currentDuration : 60);
        $timezone = $data->timezone ?? $appointment->timezone;

        $startsAt = CarbonImmutable::parse($data->startsAt, $timezone)->utc();
        $endsAt = $startsAt->addMinutes($duration);
        $localStart = $startsAt->setTimezone($timezone);

        $day = $this->availability->resolve($localStart);
        $dayStart = $localStart->startOfDay()->utc();
        $dayEnd = $localStart->endOfDay()->utc();

        $this->guard->assertBookable(
            new BookingIntent(
                startsAt: $startsAt,
                endsAt: $endsAt,
                travelBufferMinutes: (int) config('lead-appointment.travel_buffer_minutes', 60),
                dailyCapacity: (int) config('lead-appointment.daily_inspection_capacity', 1),
                isEmergency: false,
            ),
            $day,
            $this->appointments->existingIntervalsForDay($dayStart, $dayEnd, $appointment->uuid),
            $this->appointments->dailyCountForDay($dayStart, $dayEnd, $appointment->uuid),
        );

        $updated = $this->appointmentWriter->reschedule(
            $uuid,
            $startsAt,
            $endsAt,
            $timezone,
            $data->reason,
        );

        $this->events->dispatch(new AppointmentRescheduled($uuid, $data->reason));

        return $updated;
    }
}
