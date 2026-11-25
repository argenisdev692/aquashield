<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;

final readonly class CompleteAppointmentAction
{
    public function __construct(
        private AppointmentReader $appointments,
        private AppointmentWriter $appointmentWriter,
        private StatusTransition $transitions,
    ) {}

    public function execute(string $uuid): AppointmentData
    {
        $appointment = $this->appointments->find($uuid);

        if ($appointment === null) {
            throw new AppointmentNotFoundException;
        }

        $this->transitions->assertAppointment(
            AppointmentStatus::from($appointment->status),
            AppointmentStatus::Completed,
        );

        $updated = $this->appointmentWriter->updateStatus($uuid, AppointmentStatus::Completed);

        return $updated;
    }
}
