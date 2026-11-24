<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerOpenException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Circuit-breaker decorator around the provider mail transport.
 *
 * Wrapping the transport — not each job or adapter — covers every outbound
 * mail path: MailInterface, notifications and queued Mailables all resolve the
 * configured `resend` mailer. The HTTP timeout lives in the Guzzle client built
 * by AppServiceProvider; this decorator only owns the failure state.
 */
final readonly class CircuitBreakerTransport implements TransportInterface
{
    public function __construct(
        private TransportInterface $inner,
        private CircuitBreakerInterface $breaker,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            return $this->breaker->call(
                'mail:resend',
                fn (): ?SentMessage => $this->inner->send($message, $envelope),
            );
        } catch (CircuitBreakerOpenException $exception) {
            // TransportInterface promises TransportExceptionInterface. The
            // breaker's RuntimeException would be missed by Symfony's Mailer,
            // so translate it while keeping the original as the previous.
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function __toString(): string
    {
        return (string) $this->inner;
    }
}
