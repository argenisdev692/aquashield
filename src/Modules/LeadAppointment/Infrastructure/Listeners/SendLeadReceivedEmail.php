<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Domain\Events\LeadCaptured;
use Modules\LeadAppointment\Infrastructure\Mail\LeadReceivedMail;
use Shared\Infrastructure\Mail\MailInterface;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(60)]
final readonly class SendLeadReceivedEmail implements ShouldQueue
{
    public function __construct(
        private LeadReader $leads,
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
