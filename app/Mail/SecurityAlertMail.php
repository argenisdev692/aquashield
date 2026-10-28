<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\SecurityEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, string|null> $context
     */
    public function __construct(
        public string $recipientName,
        public SecurityEvent $event,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->event->subject());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.security-alert');
    }
}
