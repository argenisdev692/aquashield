<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Events\LeadCaptured;
use Modules\LeadAppointment\Infrastructure\Mail\NewLeadMail;
use Modules\LeadAppointment\Infrastructure\Mail\OfficeMailRecipients;
use Shared\Infrastructure\Mail\MailInterface;

final readonly class NotifyOfficeOfNewLead
{
    public function __construct(
        private LeadRepository $leads,
        private MailInterface $mail,
    ) {}

    public function handle(LeadCaptured $event): void
    {
        $lead = $this->leads->find($event->leadUuid);

        if ($lead === null) {
            return;
        }

        $recipients = OfficeMailRecipients::resolve();

        if ($recipients === null) {
            return;
        }

        $this->mail->queue($recipients->to, new NewLeadMail($lead), $recipients->cc);
    }
}
