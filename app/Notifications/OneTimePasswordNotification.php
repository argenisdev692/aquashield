<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Spatie\OneTimePasswords\Notifications\OneTimePasswordNotification as BaseNotification;

/**
 * The package still creates and expires the code; only the mail rendering is
 * replaced so every transactional email shares `emails.layout`.
 */
final class OneTimePasswordNotification extends BaseNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your AquaShield sign-in code')
            ->view('emails.otp', [
                'recipientName' => (string) data_get($notifiable, 'name', 'there'),
                'code' => $this->oneTimePassword->password,
                'expiresInMinutes' => (int) config('one-time-passwords.default_expires_in_minutes', 2),
            ]);
    }
}
