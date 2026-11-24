<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Domain\Enums\AppointmentStatus;
use Modules\LeadAppointment\Domain\Events\AppointmentCompleted;
use Modules\LeadAppointment\Domain\Exceptions\AppointmentNotFoundException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;

final readonly class CompleteAppointmentAction
{
    public function __construct(
        private AppointmentRepository $appointments,
        private StatusTransition $transitions,
        private Dispatcher $events,
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

        $updated = $this->appointments->updateStatus($uuid, AppointmentStatus::Completed);
        $this->events->dispatch(new AppointmentCompleted($uuid));

        return $updated;
    }
}
