<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as Cache;
use Shared\Infrastructure\Mail\CircuitBreakerTransport;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreaker;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerState;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

uses(TestCase::class);

it('opens the mail circuit after repeated transport failures', function (): void {
    $inner = new class implements TransportInterface
    {
        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
        {
            throw new TransportException('provider down');
        }

        public function __toString(): string
        {
            return 'fake';
        }
    };

    $breaker = new CircuitBreaker(app(Cache::class), failureThreshold: 2, cooldownSeconds: 60);
    $transport = new CircuitBreakerTransport($inner, $breaker);

    foreach (range(1, 2) as $_) {
        expect(fn () => $transport->send(new RawMessage('test')))
            ->toThrow(TransportException::class);
    }

    expect($breaker->state('mail:resend'))->toBe(CircuitBreakerState::Open);

    expect(fn () => $transport->send(new RawMessage('test')))
        ->toThrow(TransportException::class, 'Circuit breaker');
});
