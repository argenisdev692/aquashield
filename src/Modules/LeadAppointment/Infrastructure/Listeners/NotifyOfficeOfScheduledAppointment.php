<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeEvent;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeMail;
use Modules\LeadAppointment\Infrastructure\Mail\OfficeMailRecipients;
use Shared\Infrastructure\Mail\MailInterface;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(60)]
final readonly class NotifyOfficeOfScheduledAppointment implements ShouldQueue
{
    public function __construct(
        private AppointmentReader $appointments,
        private MailInterface $mail,
    ) {}

    public function handle(AppointmentScheduled $event): void
    {
        $appointment = $this->appointments->find($event->appointmentUuid);

        if ($appointment === null) {
            return;
        }

        $recipients = OfficeMailRecipients::resolve();

        if ($recipients === null) {
            return;
        }

        $this->mail->queue(
            $recipients->to,
            new AppointmentOfficeMail($appointment, AppointmentOfficeEvent::Scheduled),
            $recipients->cc,
        );
    }
}
