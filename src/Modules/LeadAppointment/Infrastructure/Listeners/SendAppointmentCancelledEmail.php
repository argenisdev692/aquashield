<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentCancelledMail;
use Shared\Infrastructure\Mail\MailInterface;

final readonly class SendAppointmentCancelledEmail
{
    public function __construct(
        private AppointmentRepository $appointments,
        private MailInterface $mail,
    ) {}

    public function handle(AppointmentCancelled $event): void
    {
        $appointment = $this->appointments->find($event->appointmentUuid);

        if ($appointment === null || $appointment->customerEmail === null) {
            return;
        }

        $this->mail->queue(
            $appointment->customerEmail,
            new AppointmentCancelledMail($appointment, $event->reason),
        );
    }
}
