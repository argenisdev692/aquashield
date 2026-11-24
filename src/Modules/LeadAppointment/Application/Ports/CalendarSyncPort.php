<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Modules\LeadAppointment\Application\Dtos\AppointmentData;

interface CalendarSyncPort
{
    /** @return string|null Google event id, or null when sync is disabled/failed. */
    public function create(AppointmentData $appointment): ?string;

    public function update(AppointmentData $appointment): void;

    public function delete(AppointmentData $appointment): void;
}
