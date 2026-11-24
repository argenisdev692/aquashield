<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Modules\LeadAppointment\Domain\Exceptions\LeadNotFoundException;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\Services\BookingGuard;
use Modules\LeadAppointment\Domain\Services\StatusTransition;
use Modules\LeadAppointment\Domain\ValueObjects\BookingIntent;

/**
 * The scheduling use case. The guard sees only domain VOs; the repository owns
 * the transaction and the lead row lock. The lead status is advanced only
 * after the appointment is persisted.
 *
 * Technician assignment is deliberately absent: it belongs to the Claim +
 * Scope Sheet phase, where the assigned person is a `User` with the
 * TECHNICAL_SERVICES role — not a row in a separate technicians table.
 */
final readonly class ScheduleAppointmentAction
{
    public function __construct(
        private LeadRepository $leads,
        private AppointmentRepository $appointments,
        private AvailabilityResolver $availability,
        private BookingGuard $guard,
        private StatusTransition $transitions,
        private Dispatcher $events,
    ) {}

    public function execute(ScheduleAppointmentData $data, ?int $actorId = null): AppointmentData
    {
        if ($data->startsAt === null || $data->startsAt === '') {
            throw new \InvalidArgumentException('A start time is required.');
        }

        $lead = $this->leads->find($data->leadUuid);

        if ($lead === null) {
            throw new LeadNotFoundException;
        }

        if ($lead->region !== null) {
            $region = Region::from($lead->region);
        } elseif ($data->region !== null) {
            $region = Region::from($data->region);
        } else {
            $region = Region::from((string) config('lead-appointment.default_region', Region::Houston->value));
        }

        $duration = $data->durationMinutes
            ?? (int) config('lead-appointment.inspection_duration_minutes', 420);
        $timezone = $data->timezone ?? $region->timezone();

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
                isEmergency: $data->isEmergency,
            ),
            $day,
            $this->appointments->existingIntervalsForDay($dayStart, $dayEnd),
            $this->appointments->dailyCountForDay($dayStart, $dayEnd),
        );

        $this->transitions->assertLead(LeadStatus::from($lead->status), LeadStatus::InspectionScheduled);

        $appointment = $this->appointments->createWithLock(
            $data,
            $duration,
            $timezone,
            $region->value,
            $actorId,
        );

        $this->leads->markInspectionScheduled($lead->uuid);
        $this->events->dispatch(new AppointmentScheduled($appointment->uuid));

        return $appointment;
    }
}
