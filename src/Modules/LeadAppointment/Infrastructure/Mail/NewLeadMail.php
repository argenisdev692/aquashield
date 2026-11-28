<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\LeadAppointment\Application\Dtos\LeadData;

final class NewLeadMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeadData $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New lead — '.$this->lead->fullName.' ('.ucfirst((string) ($this->lead->region ?? config('lead-appointment.default_region', 'houston'))).')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lead-appointment.new-lead');
    }
}
