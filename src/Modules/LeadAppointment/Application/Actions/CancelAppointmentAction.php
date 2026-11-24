<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;

final readonly class CancelAppointmentAction
{
    public function __construct(
        private AppointmentRepository $appointments,
        private StatusTransition $transitions,
        private Dispatcher $events,
    ) {}

    public function execute(string $uuid, string $reason): AppointmentData
    {
        $appointment = $this->appointments->find($uuid);

        if ($appointment === null) {
            throw new AppointmentNotFoundException;
        }

        $this->transitions->assertAppointment(
            AppointmentStatus::from($appointment->status),
            AppointmentStatus::Cancelled,
        );

        $updated = $this->appointments->updateStatus($uuid, AppointmentStatus::Cancelled, $reason);
        $this->events->dispatch(new AppointmentCancelled($uuid, $reason));

        return $updated;
    }
}
