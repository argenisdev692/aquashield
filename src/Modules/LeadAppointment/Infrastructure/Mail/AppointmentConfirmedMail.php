<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;

final class AppointmentConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AppointmentData $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your inspection is scheduled — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-appointment.appointment-confirmed');
    }

    /**
     * The appointment sheet is a real attachment, rendered from the same
     * corporate PDF layout the exports use. It is generated in the mail layer
     * so a queued mailable carries the bytes, not a second job.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('exports.lead-appointment.appointment', [
            'appointment' => $this->appointment,
        ])->setPaper('a4', 'portrait')->output();

        return [
            Attachment::fromData(static fn (): string => $pdf, 'appointment-'.$this->appointment->uuid.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
