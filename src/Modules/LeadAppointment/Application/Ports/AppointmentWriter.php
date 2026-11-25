<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;

/**
 * The write side of the appointment aggregate. `createWithLock` owns the
 * transaction that inserts the appointment and advances the lead status.
 */
interface AppointmentWriter
{
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
}
