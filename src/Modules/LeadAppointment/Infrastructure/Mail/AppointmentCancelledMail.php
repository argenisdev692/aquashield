<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;

final class AppointmentCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AppointmentData $appointment,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your appointment was cancelled — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-appointment.appointment-cancelled');
    }
}
