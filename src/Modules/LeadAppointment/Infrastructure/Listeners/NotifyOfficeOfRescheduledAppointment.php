<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeEvent;
use Modules\LeadAppointment\Infrastructure\Mail\AppointmentOfficeMail;
use Modules\LeadAppointment\Infrastructure\Mail\OfficeMailRecipients;
use Shared\Infrastructure\Mail\MailInterface;

final readonly class NotifyOfficeOfRescheduledAppointment
{
    public function __construct(
        private AppointmentRepository $appointments,
        private MailInterface $mail,
    ) {}

    public function handle(AppointmentRescheduled $event): void
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
            new AppointmentOfficeMail($appointment, AppointmentOfficeEvent::Rescheduled, $event->reason),
            $recipients->cc,
        );
    }
}
