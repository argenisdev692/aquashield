<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Application\Dtos\CalendarEventData;
use Modules\LeadAppointment\Application\Dtos\RescheduleAppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\Events\AppointmentDeleted;
use Modules\LeadAppointment\Domain\Events\AppointmentRestored;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Exceptions\InvalidSchedulingTimezoneException;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\LeadAppointment\Domain\Exceptions\LeadNotFoundException;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\Services\BookingGuard;
use Modules\LeadAppointment\Domain\Services\StatusTransition;
use Modules\LeadAppointment\Domain\ValueObjects\BookingIntent;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\CalendarLock;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;

final readonly class EloquentAppointmentRepository implements AppointmentReader, AppointmentWriter
{
    public function __construct(
        private CalendarLock $calendarLock,
        private AvailabilityResolver $availability,
        private BookingGuard $guard,
        private StatusTransition $transitions,
        private Dispatcher $events,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AppointmentData>
     */
    public function paginate(AppointmentFilterData $filters): LengthAwarePaginator
    {
        return Appointment::query()
            ->applyFilters($filters)
            ->with([
                'lead:id,uuid,first_name,last_name,email,phone,address,address_2,city,state,zip_code',
            ])
            ->select([
                'id', 'uuid', 'lead_id', 'kind', 'status',
                'starts_at', 'ends_at', 'timezone', 'previous_starts_at', 'previous_ends_at',
                'region', 'google_event_id', 'cancel_reason', 'notes', 'created_at', 'deleted_at',
            ])
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->withQueryString()
            ->through(fn (Appointment $appointment): AppointmentData => $this->toData($appointment));
    }

    public function find(string $uuid, bool $withTrashed = false): ?AppointmentData
    {
        $query = Appointment::query()
            ->with([
                'lead:id,uuid,first_name,last_name,email,phone,address,address_2,city,state,zip_code',
            ])
            ->where('uuid', $uuid);

        if ($withTrashed) {
            $query->withTrashed();
        }

        $appointment = $query->first();

        return $appointment === null ? null : $this->toData($appointment);
    }

    public function createWithLock(
        ScheduleAppointmentData $data,
        ?int $createdBy,
    ): AppointmentData {
        if ($data->startsAt === null || $data->startsAt === '') {
            throw new \InvalidArgumentException('A start time is required to schedule an appointment.');
        }

        return DB::transaction(function () use ($data, $createdBy): AppointmentData {
            $this->calendarLock->acquire();

            $lead = Lead::query()->where('uuid', $data->leadUuid)->lockForUpdate()->first();

            if ($lead === null) {
                throw new LeadNotFoundException;
            }

            $region = $lead->region instanceof Region
                ? $lead->region
                : Region::from($data->region ?? (string) config('lead-appointment.default_region', Region::Houston->value));
            $duration = $data->durationMinutes
                ?? (int) config('lead-appointment.inspection_duration_minutes', 420);
            $timezone = $data->timezone ?? $region->timezone();
            $this->assertTimezoneForRegion($region, $timezone);
            $startsAt = CarbonImmutable::parse($data->startsAt, $timezone)->utc();
            $endsAt = $startsAt->addMinutes($duration);

            $this->transitions->assertLead($lead->status, LeadStatus::InspectionScheduled);
            $this->assertBookingAllowed($startsAt, $endsAt, $timezone, null, $data->isEmergency);

            $appointment = Appointment::query()->create([
                'lead_id' => $lead->id,
                'kind' => $data->kind->value,
                'status' => AppointmentStatus::Scheduled->value,
                'is_emergency' => $data->isEmergency,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'region' => $region->value,
                'notes' => $data->notes,
                'created_by' => $createdBy,
            ]);

            $lead->status = LeadStatus::InspectionScheduled;
            $lead->save();

            return $this->find($appointment->uuid)
                ?? throw new \RuntimeException('Appointment was not persisted.');
        });
    }

    public function reschedule(
        string $uuid,
        RescheduleAppointmentData $data,
    ): AppointmentData {
        return DB::transaction(function () use ($uuid, $data): AppointmentData {
            $this->calendarLock->acquire();

            $appointment = Appointment::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($appointment === null) {
                throw new AppointmentNotFoundException;
            }

            $status = $appointment->status;

            if (! in_array($status, [AppointmentStatus::Requested, AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
                throw InvalidStatusTransitionException::for($status->value, 'rescheduled');
            }

            $currentDuration = (int) CarbonImmutable::parse($appointment->starts_at)
                ->diffInMinutes(CarbonImmutable::parse($appointment->ends_at));
            $duration = $data->durationMinutes ?? ($currentDuration > 0 ? $currentDuration : 60);
            $timezone = $data->timezone ?? $appointment->timezone;
            $this->assertTimezoneForRegion($appointment->region, $timezone);
            $startsAt = CarbonImmutable::parse($data->startsAt, $timezone)->utc();
            $endsAt = $startsAt->addMinutes($duration);

            $this->assertBookingAllowed($startsAt, $endsAt, $timezone, $appointment->uuid, $appointment->is_emergency);

            $appointment->previous_starts_at = $appointment->starts_at;
            $appointment->previous_ends_at = $appointment->ends_at;
            $appointment->starts_at = $startsAt;
            $appointment->ends_at = $endsAt;
            $appointment->timezone = $timezone;
            $appointment->status = $status === AppointmentStatus::Confirmed
                ? AppointmentStatus::Confirmed
                : AppointmentStatus::Scheduled;
            $appointment->notes = trim(($appointment->notes ?? '')."\nRescheduled: ".$data->reason);
            $appointment->save();

            return $this->find($appointment->uuid)
                ?? throw new \RuntimeException('Appointment was not persisted.');
        });
    }

    public function updateStatus(
        string $uuid,
        AppointmentStatus $status,
        ?string $reason = null,
    ): AppointmentData {
        return DB::transaction(function () use ($uuid, $status, $reason): AppointmentData {
            $this->calendarLock->acquire();

            $appointment = Appointment::query()->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            $this->transitions->assertAppointment($appointment->status, $status);
            $appointment->status = $status;

            if ($status === AppointmentStatus::Cancelled) {
                $appointment->cancelled_at = CarbonImmutable::now();
                $appointment->cancel_reason = $reason;
            }

            if ($status === AppointmentStatus::Completed) {
                $appointment->completed_at = CarbonImmutable::now();
            }

            $appointment->save();

            return $this->find($appointment->uuid)
                ?? throw new \RuntimeException('Appointment was not persisted.');
        });
    }

    /**
     * @return list<TimeInterval>
     */
    public function existingIntervalsForDay(
        CarbonImmutable $dayStart,
        CarbonImmutable $dayEnd,
        ?string $excludeUuid = null,
    ): array {
        return Appointment::query()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->whereNotIn('status', $this->inactiveStatuses())
            ->when($excludeUuid !== null, fn ($query) => $query->where('uuid', '!=', $excludeUuid))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment): TimeInterval => new TimeInterval(
                CarbonImmutable::parse($appointment->starts_at),
                CarbonImmutable::parse($appointment->ends_at),
            ))
            ->values()
            ->all();
    }

    public function dailyCountForDay(
        CarbonImmutable $dayStart,
        CarbonImmutable $dayEnd,
        ?string $excludeUuid = null,
    ): int {
        return Appointment::query()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->whereNotIn('status', $this->inactiveStatuses())
            ->when($excludeUuid !== null, fn ($query) => $query->where('uuid', '!=', $excludeUuid))
            ->count();
    }

    /**
     * @return list<AppointmentData>
     */
    public function calendarBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region,
    ): array {
        return Appointment::query()
            ->with([
                'lead:id,uuid,first_name,last_name,email,phone,address,address_2,city,state,zip_code',
            ])
            ->where('starts_at', '>=', $from->utc())
            ->where('starts_at', '<=', $to->utc())
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment): AppointmentData => $this->toData($appointment))
            ->values()
            ->all();
    }

    /**
     * @return list<CalendarEventData>
     */
    public function calendarEventsBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region = null,
    ): array {
        return Appointment::query()
            ->with('lead:id,uuid,first_name,last_name')
            ->where('starts_at', '>=', $from->utc())
            ->where('starts_at', '<=', $to->utc())
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->orderBy('starts_at')
            ->get(['id', 'uuid', 'lead_id', 'starts_at', 'timezone', 'status', 'kind', 'region'])
            ->map(function (Appointment $appointment): CalendarEventData {
                $name = trim($appointment->lead->first_name.' '.$appointment->lead->last_name);

                return new CalendarEventData(
                    uuid: $appointment->uuid,
                    leadUuid: $appointment->lead->uuid,
                    customerName: $name === '' ? 'Unknown customer' : $name,
                    startsAtLocal: $appointment->starts_at->setTimezone($appointment->timezone)->toIso8601String(),
                    timezone: $appointment->timezone,
                    status: $appointment->status->value,
                    kind: $appointment->kind->value,
                    region: $appointment->region->value,
                );
            })
            ->values()
            ->all();
    }

    public function countScheduledBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?AppointmentKind $kind = null,
        ?string $region = null,
    ): int {
        return Appointment::query()
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind->value))
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function dailyScheduledBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?AppointmentKind $kind = null,
        ?string $region = null,
    ): array {
        return Appointment::query()
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind->value))
            ->when($region !== null, fn ($query) => $query->where('region', $region))
            ->pluck('starts_at')
            ->map(
                static fn (mixed $startsAt): string => CarbonImmutable::parse((string) $startsAt)
                    ->setTimezone($timezone)
                    ->toDateString(),
            )
            ->countBy()
            ->all();
    }

    public function setGoogleEventId(string $uuid, ?string $eventId): void
    {
        Appointment::query()->where('uuid', $uuid)->update(['google_event_id' => $eventId]);
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid): void {
            $this->calendarLock->acquire();

            $appointment = Appointment::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($appointment === null) {
                return;
            }

            $appointment->delete();
            $this->events->dispatch(new AppointmentDeleted($appointment->uuid));
        });
    }

    public function restore(string $uuid): AppointmentData
    {
        return DB::transaction(function () use ($uuid): AppointmentData {
            $this->calendarLock->acquire();

            $appointment = Appointment::onlyTrashed()->where('uuid', $uuid)->lockForUpdate()->firstOrFail();

            if ($appointment->status !== AppointmentStatus::Cancelled) {
                $this->assertBookingAllowed(
                    CarbonImmutable::parse($appointment->starts_at),
                    CarbonImmutable::parse($appointment->ends_at),
                    $appointment->timezone,
                    $appointment->uuid,
                    $appointment->is_emergency,
                );
            }

            $appointment->restore();
            $this->events->dispatch(new AppointmentRestored($appointment->uuid));

            return $this->find($appointment->uuid)
                ?? throw new \RuntimeException('Appointment was not restored.');
        });
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDelete(array $uuids): void
    {
        DB::transaction(function () use ($uuids): void {
            $this->calendarLock->acquire();

            foreach (Appointment::query()->whereIn('uuid', $uuids)->get() as $appointment) {
                $appointment->delete();
                $this->events->dispatch(new AppointmentDeleted($appointment->uuid));
            }
        });
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestore(array $uuids): void
    {
        DB::transaction(function () use ($uuids): void {
            $this->calendarLock->acquire();

            $appointments = Appointment::onlyTrashed()->whereIn('uuid', $uuids)->lockForUpdate()->get();

            foreach ($appointments as $appointment) {
                if ($appointment->status !== AppointmentStatus::Cancelled) {
                    $this->assertBookingAllowed(
                        CarbonImmutable::parse($appointment->starts_at),
                        CarbonImmutable::parse($appointment->ends_at),
                        $appointment->timezone,
                        $appointment->uuid,
                        $appointment->is_emergency,
                    );
                }

                $appointment->restore();
                $this->events->dispatch(new AppointmentRestored($appointment->uuid));
            }
        });
    }

    public function count(AppointmentFilterData $filters): int
    {
        return Appointment::query()->applyFilters($filters)->count();
    }

    /**
     * @return iterable<int, AppointmentData>
     */
    public function stream(AppointmentFilterData $filters): iterable
    {
        foreach (Appointment::query()
            ->applyFilters($filters)
            ->with([
                'lead:id,uuid,first_name,last_name,email,phone,address,address_2,city,state,zip_code',
            ])
            ->lazy(500) as $appointment) {
            yield $this->toData($appointment);
        }
    }

    private function assertTimezoneForRegion(Region $region, string $timezone): void
    {
        if (! $region->allowsTimezone($timezone)) {
            throw InvalidSchedulingTimezoneException::for($region, $timezone);
        }
    }

    /**
     * Final availability and capacity validation, inside the company-calendar
     * lock. Completed and no-show visits still consume the day's capacity.
     */
    private function assertBookingAllowed(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
        ?string $excludeUuid,
        bool $isEmergency,
    ): void {
        $travelBufferMinutes = (int) config('lead-appointment.travel_buffer_minutes', 60);
        $localStart = $startsAt->setTimezone($timezone);
        $localBlockedEnd = $endsAt->addMinutes($travelBufferMinutes)->setTimezone($timezone);
        $cursor = $localStart->startOfDay();
        $lastDay = $localBlockedEnd->startOfDay();
        $intent = new BookingIntent(
            startsAt: $startsAt,
            endsAt: $endsAt,
            travelBufferMinutes: $travelBufferMinutes,
            dailyCapacity: (int) config('lead-appointment.daily_inspection_capacity', 1),
            isEmergency: $isEmergency,
        );

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $day = $this->availability->resolve($cursor);
            $dayStart = $cursor->utc();
            $dayEnd = $cursor->endOfDay()->utc();
            $intervals = $this->existingIntervalsForDay($dayStart, $dayEnd, $excludeUuid);

            $this->guard->assertBookable(
                $intent,
                $day,
                $intervals,
                count($intervals),
            );

            $cursor = $cursor->addDay();
        }
    }

    /**
     * @return list<string>
     */
    private function inactiveStatuses(): array
    {
        return [AppointmentStatus::Cancelled->value];
    }

    private function toData(Appointment $appointment): AppointmentData
    {
        $lead = $appointment->lead;
        $address = collect([
            $lead->address,
            $lead->address_2,
            $lead->city,
            $lead->state,
            $lead->zip_code,
        ])->filter(static fn (mixed $value): bool => $value !== null && $value !== '')->implode(', ');

        return new AppointmentData(
            uuid: $appointment->uuid,
            leadUuid: $lead->uuid,
            customerName: trim($lead->first_name.' '.$lead->last_name),
            customerEmail: $lead->email,
            customerPhone: $lead->phone,
            addressLine: $address,
            kind: $appointment->kind->value,
            status: $appointment->status->value,
            startsAt: $appointment->starts_at->utc()->toIso8601String(),
            endsAt: $appointment->ends_at->utc()->toIso8601String(),
            startsAtLocal: $appointment->starts_at->setTimezone($appointment->timezone)->toIso8601String(),
            endsAtLocal: $appointment->ends_at->setTimezone($appointment->timezone)->toIso8601String(),
            timezone: $appointment->timezone,
            region: $appointment->region->value,
            previousStartsAt: $appointment->previous_starts_at?->utc()->toIso8601String(),
            googleEventId: $appointment->google_event_id,
            notes: $appointment->notes,
            cancelReason: $appointment->cancel_reason,
            createdAt: $appointment->created_at?->toIso8601String(),
            deletedAt: $appointment->deleted_at?->toIso8601String(),
        );
    }
}
