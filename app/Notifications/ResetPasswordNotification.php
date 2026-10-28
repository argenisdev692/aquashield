<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The only password-reset email the product sends.
 *
 * A password is never in the message, because there is no password to send:
 * the recipient chooses it from a single-use, expiring link. The same
 * notification serves the administrator-created invitation and the public
 * "forgot password" flow, so the two cannot drift.
 *
 * The URL is built from `app.url`, never from the request Host header. OWASP's
 * Forgot Password Cheat Sheet calls out Host Header Injection as a reset-link
 * vulnerability, and an attacker-controlled Host would otherwise produce a
 * link that points at their domain.
 */
final class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expiresIn = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        return (new MailMessage)
            ->subject('Set your '.config('app.name').' password')
            ->view('emails.reset-password', [
                'recipientName' => $notifiable->name,
                'url' => $this->resetUrl(),
                'expiresIn' => $expiresIn,
            ]);
    }

    private function resetUrl(): string
    {
        /*
         * No email in the URL: OWASP's Forgot Password Cheat Sheet warns that
         * PII in recovery links leaks through logs and referrers. The reset form
         * asks for the address; the token alone identifies the request.
         */
        $path = route('password.reset', [
            'token' => $this->token,
        ], false);

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
