<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Users\Support\InvitationLink;

/**
 * The administrator invitation email.
 *
 * It carries a signed, expiring activation URL — never a password, never the
 * recipient's e-mail address (PII in a recovery URL leaks through logs and
 * Referer headers). The URL is generated server-side from the configured app
 * root, so an attacker-controlled Host header cannot redirect the link.
 *
 * The signed URL is single-use in practice because the activation endpoint
 * compares the embedded `invitation` timestamp with `users.invitation_sent_at`
 * and clears the column on success. A resend rewrites the column, which
 * invalidates every older link immediately.
 */
final class UserInvitationNotification extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly string $recipientName,
        public readonly int $expiresInHours = InvitationLink::EXPIRES_IN_HOURS,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activate your '.config('app.name').' account')
            ->view('emails.user-invitation', [
                'recipientName' => $this->recipientName,
                'url' => $this->url,
                'expiresInHours' => $this->expiresInHours,
            ]);
    }
}
