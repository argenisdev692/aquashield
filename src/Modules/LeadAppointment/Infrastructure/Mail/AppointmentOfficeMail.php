<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;

final class AppointmentOfficeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AppointmentData $appointment,
        public AppointmentOfficeEvent $event,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->event->heading().' — '.$this->appointment->customerName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-appointment.appointment-office');
    }
}
