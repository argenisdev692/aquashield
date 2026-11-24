<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailable;

/**
 * Cross-cutting outbound mail contract.
 *
 * The configured provider is Resend (`mail.default=resend`). Tests and local
 * log mode use the `array`/`log` mailer through ResolvesOutboundMailer, so no
 * test opens a provider connection. Callers never import a concrete transport.
 */
interface MailInterface
{
    /**
     * Deliver immediately via the configured provider mailer.
     * Prefer calling this from a queued listener (`ShouldQueue` + `onQueue('emails')`).
     *
     * @param list<string>|string $to
     * @param list<string>|string|null $bcc
     */
    public function send(string|array $to, Mailable $mailable, string|array|null $bcc = null): void;

    /**
     * Push the mailable onto the queue via the configured provider mailer.
     *
     * @param list<string>|string $to
     * @param list<string>|string|null $cc
     */
    public function queue(string|array $to, Mailable $mailable, string|array|null $cc = null): void;
}
