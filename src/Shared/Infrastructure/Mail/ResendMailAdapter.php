<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;

/**
 * MailInterface over the `resend` mailer — the Resend HTTPS API.
 *
 * This is the only outbound adapter. The provider is selected by the Laravel
 * mailer (`MAIL_MAILER=resend` in production), and the shared mail transport
 * adds the HTTP timeout and circuit breaker. Callers keep depending on
 * {@see MailInterface} and never name a provider.
 *
 * In PHPUnit (`MAIL_MAILER=array`) and local log mode the default mailer is
 * honored so tests never reach the Resend API.
 */
final readonly class ResendMailAdapter implements MailInterface
{
    use ResolvesOutboundMailer;

    private const string MAILER = 'resend';

    public function __construct(private MailFactory $mail) {}

    public function send(string|array $to, Mailable $mailable, string|array|null $bcc = null): void
    {
        $pending = $this->mail->mailer($this->resolveOutboundMailer(self::MAILER))->to($to);

        if ($bcc !== null && $bcc !== [] && $bcc !== '') {
            $pending->bcc($bcc);
        }

        $pending->send($mailable);
    }

    public function queue(string|array $to, Mailable $mailable, string|array|null $cc = null): void
    {
        $pending = $this->mail->mailer($this->resolveOutboundMailer(self::MAILER))->to($to);

        if ($cc !== null && $cc !== [] && $cc !== '') {
            $pending->cc($cc);
        }

        $pending->queue($mailable);
    }
}
