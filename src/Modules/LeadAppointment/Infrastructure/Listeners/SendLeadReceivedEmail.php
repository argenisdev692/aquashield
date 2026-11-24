<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Events\LeadCaptured;
use Modules\LeadAppointment\Infrastructure\Mail\LeadReceivedMail;
use Shared\Infrastructure\Mail\MailInterface;

final readonly class SendLeadReceivedEmail
{
    public function __construct(
        private LeadRepository $leads,
        private MailInterface $mail,
    ) {}

    public function handle(LeadCaptured $event): void
    {
        $lead = $this->leads->find($event->leadUuid);

        if ($lead === null || $lead->email === null) {
            return;
        }

        $this->mail->queue($lead->email, new LeadReceivedMail($lead));
    }
}
