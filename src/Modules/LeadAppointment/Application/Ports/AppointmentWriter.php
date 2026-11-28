<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\RescheduleAppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;

/**
 * The write side of the appointment aggregate. Scheduling, rescheduling,
 * restoring and capacity-changing writes own the company-calendar lock and
 * validate availability inside the same transaction.
 */
interface AppointmentWriter
{
    public function createWithLock(
        ScheduleAppointmentData $data,
        ?int $createdBy,
    ): AppointmentData;

    public function reschedule(
        string $uuid,
        RescheduleAppointmentData $data,
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
