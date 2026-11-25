<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentConfirmedMail;
use Shared\Infrastructure\Mail\MailInterface;

final readonly class SendAppointmentConfirmation
{
    public function __construct(
        private AppointmentReader $appointments,
        private MailInterface $mail,
    ) {}

    public function handle(AppointmentScheduled $event): void
    {
        $appointment = $this->appointments->find($event->appointmentUuid);

        if ($appointment === null || $appointment->customerEmail === null) {
            return;
        }

        $this->mail->queue($appointment->customerEmail, new AppointmentConfirmedMail($appointment));
    }
}
