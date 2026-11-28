<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\RescheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;

final readonly class RescheduleAppointmentAction
{
    public function __construct(
        private AppointmentWriter $appointmentWriter,
        private Dispatcher $events,
    ) {}

    public function execute(string $uuid, RescheduleAppointmentData $data): AppointmentData
    {
        $updated = $this->appointmentWriter->reschedule($uuid, $data);

        $this->events->dispatch(new AppointmentRescheduled($uuid, $data->reason));

        return $updated;
    }
}
