<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;

/**
 * The read side of the appointment aggregate: lists, calendar, capacity reads
 * and the export stream.
 */
interface AppointmentReader
{
    /**
     * @return LengthAwarePaginator<int, AppointmentData>
     */
    public function paginate(AppointmentFilterData $filters): LengthAwarePaginator;

    public function find(string $uuid, bool $withTrashed = false): ?AppointmentData;

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

    public function count(AppointmentFilterData $filters): int;

    /**
     * Lazy export stream; same filters as the list.
     *
     * @return iterable<int, AppointmentData>
     */
    public function stream(AppointmentFilterData $filters): iterable;
}
