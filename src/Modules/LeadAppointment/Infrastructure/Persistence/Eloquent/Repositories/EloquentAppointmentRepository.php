<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Exceptions\CapacityExceededException;
use Modules\LeadAppointment\Domain\Exceptions\SlotUnavailableException;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;

final readonly class EloquentAppointmentRepository implements AppointmentRepository
{
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
        int $durationMinutes,
        string $timezone,
        string $region,
        ?int $createdBy,
    ): AppointmentData {
        if ($data->startsAt === null) {
            throw new \InvalidArgumentException('A start time is required to schedule an appointment.');
        }

        return DB::transaction(function () use ($data, $durationMinutes, $timezone, $region, $createdBy): AppointmentData {
            // Technicians are `users` with the TECHNICAL_SERVICES role when the
            // assignment phase lands. Until then the lead row is the lock that
            // serialises bookings for the same customer.
            $lead = Lead::query()->where('uuid', $data->leadUuid)->lockForUpdate()->firstOrFail();

            $startsAt = CarbonImmutable::parse($data->startsAt, $timezone)->utc();
            $endsAt = $startsAt->addMinutes($durationMinutes);

            $this->assertDaySlotAvailable($startsAt, $endsAt, $timezone, null, $data->isEmergency);

            $appointment = Appointment::query()->create([
                'lead_id' => $lead->id,
                'kind' => $data->kind->value,
                'status' => AppointmentStatus::Scheduled->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'region' => $region,
                'notes' => $data->notes,
                'created_by' => $createdBy,
            ]);

            return $this->find($appointment->uuid)
                ?? throw new \RuntimeException('Appointment was not persisted.');
        });
    }

    public function reschedule(
        string $uuid,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
        string $reason,
    ): AppointmentData {
        return DB::transaction(function () use ($uuid, $startsAt, $endsAt, $timezone, $reason): AppointmentData {
            $appointment = Appointment::query()->where('uuid', $uuid)->lockForUpdate()->firstOrFail();

            $this->assertDaySlotAvailable($startsAt, $endsAt, $timezone, $appointment->uuid, false);

            $appointment->previous_starts_at = $appointment->starts_at;
            $appointment->previous_ends_at = $appointment->ends_at;
            $appointment->starts_at = $startsAt;
            $appointment->ends_at = $endsAt;
            $appointment->timezone = $timezone;
            $appointment->status = $appointment->status === AppointmentStatus::Confirmed
                ? AppointmentStatus::Confirmed
                : AppointmentStatus::Scheduled;
            $appointment->notes = trim(($appointment->notes ?? '')."\nRescheduled: ".$reason);
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
        $appointment = Appointment::query()->where('uuid', $uuid)->firstOrFail();
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

    public function countScheduledBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?AppointmentKind $kind = null,
    ): int {
        return Appointment::query()
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind->value))
            ->count();
    }

    public function setGoogleEventId(string $uuid, ?string $eventId): void
    {
        Appointment::query()->where('uuid', $uuid)->update(['google_event_id' => $eventId]);
    }

    public function delete(string $uuid): void
    {
        Appointment::query()->where('uuid', $uuid)->delete();
    }

    public function restore(string $uuid): AppointmentData
    {
        $appointment = Appointment::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $appointment->restore();

        return $this->find($appointment->uuid)
            ?? throw new \RuntimeException('Appointment was not restored.');
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkDelete(array $uuids): void
    {
        Appointment::query()->whereIn('uuid', $uuids)->delete();
    }

    /**
     * @param list<string> $uuids
     */
    public function bulkRestore(array $uuids): void
    {
        Appointment::onlyTrashed()->whereIn('uuid', $uuids)->restore();
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

    /**
     * Persistence-level re-check, inside the transaction: the domain guard
     * already validated, but a concurrent write must not slip past the single
     * crew's daily cap or the travel buffer.
     */
    private function assertDaySlotAvailable(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
        ?string $excludeUuid,
        bool $isEmergency,
    ): void {
        $localStart = $startsAt->setTimezone($timezone);
        $dayStart = $localStart->startOfDay()->utc();
        $dayEnd = $localStart->endOfDay()->utc();
        $travel = (int) config('lead-appointment.travel_buffer_minutes', 60);

        $rows = Appointment::query()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->whereNotIn('status', $this->inactiveStatuses())
            ->when($excludeUuid !== null, fn ($query) => $query->where('uuid', '!=', $excludeUuid))
            ->get(['starts_at', 'ends_at']);

        if (! $isEmergency && $rows->count() >= (int) config('lead-appointment.daily_inspection_capacity', 1)) {
            throw new CapacityExceededException;
        }

        $candidateEnd = $endsAt->addMinutes($travel);

        foreach ($rows as $row) {
            $existingStart = CarbonImmutable::parse($row->starts_at);
            $existingEnd = CarbonImmutable::parse($row->ends_at)->addMinutes($travel);

            if ($startsAt < $existingEnd && $candidateEnd > $existingStart) {
                throw SlotUnavailableException::overlap();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function inactiveStatuses(): array
    {
        return [
            AppointmentStatus::Completed->value,
            AppointmentStatus::NoShow->value,
            AppointmentStatus::Cancelled->value,
        ];
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
