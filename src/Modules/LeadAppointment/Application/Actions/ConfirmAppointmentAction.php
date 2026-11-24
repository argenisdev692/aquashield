<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;

final readonly class ConfirmAppointmentAction
{
    public function __construct(
        private AppointmentRepository $appointments,
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
            AppointmentStatus::Confirmed,
        );

        return $this->appointments->updateStatus($uuid, AppointmentStatus::Confirmed);
    }
}
