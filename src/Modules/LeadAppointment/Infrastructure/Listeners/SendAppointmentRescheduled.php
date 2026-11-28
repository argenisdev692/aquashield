<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentRescheduledMail;
use Shared\Infrastructure\Mail\MailInterface;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(60)]
final readonly class SendAppointmentRescheduled implements ShouldQueue
{
    public function __construct(
        private AppointmentReader $appointments,
        private MailInterface $mail,
    ) {}

    public function handle(AppointmentRescheduled $event): void
    {
        $appointment = $this->appointments->find($event->appointmentUuid);

        if ($appointment === null || $appointment->customerEmail === null) {
            return;
        }

        $this->mail->queue(
            $appointment->customerEmail,
            new AppointmentRescheduledMail($appointment, $event->reason),
        );
    }
}
