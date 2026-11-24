<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\LeadAppointment\Application\Dtos\LeadData;

final class LeadReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeadData $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your request — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-appointment.lead-received');
    }
}
