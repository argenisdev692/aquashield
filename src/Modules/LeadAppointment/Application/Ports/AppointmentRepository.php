<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;

interface AppointmentRepository
{
    /**
     * @return LengthAwarePaginator<int, AppointmentData>
     */
    public function paginate(AppointmentFilterData $filters): LengthAwarePaginator;

    public function find(string $uuid, bool $withTrashed = false): ?AppointmentData;

    public function createWithLock(
        ScheduleAppointmentData $data,
        int $durationMinutes,
        string $timezone,
        string $region,
        ?int $createdBy,
    ): AppointmentData;

    public function reschedule(
        string $uuid,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
        string $reason,
    ): AppointmentData;

    public function updateStatus(
        string $uuid,
        AppointmentStatus $status,
        ?string $reason = null,
    ): AppointmentData;

    /**
     * @return list<TimeInterval>
     */
    public function existingIntervalsForDay(
        CarbonImmutable $dayStart,
        CarbonImmutable $dayEnd,
        ?string $excludeUuid = null,
    ): array;

    public function dailyCountForDay(
        CarbonImmutable $dayStart,
        CarbonImmutable $dayEnd,
        ?string $excludeUuid = null,
    ): int;

    /**
     * @return list<AppointmentData>
     */
    public function calendarBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $region,
    ): array;

    /**
     * Appointments whose visit starts inside the window. Cancelled and no-show
     * rows are excluded; completed visits count, because they belong to the
     * month's workload. Null `$kind` counts every kind.
     */
    public function countScheduledBetween(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?AppointmentKind $kind = null,
    ): int;

    public function setGoogleEventId(string $uuid, ?string $eventId): void;

    public function delete(string $uuid): void;

    public function restore(string $uuid): AppointmentData;

    /**
     * @param list<string> $uuids
     */
    public function bulkDelete(array $uuids): void;

    /**
     * @param list<string> $uuids
     */
    public function bulkRestore(array $uuids): void;

    public function count(AppointmentFilterData $filters): int;

    /**
     * Lazy export stream; same filters as the list.
     *
     * @return iterable<int, AppointmentData>
     */
    public function stream(AppointmentFilterData $filters): iterable;
}
