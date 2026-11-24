<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Mail;
use Resend\Client as ResendClient;
use Resend\Contracts\Client as ResendClientContract;
use Shared\Infrastructure\Mail\CircuitBreakerTransport;

it('configures an explicit SMTP timeout', function (): void {
    expect(config('mail.mailers.smtp.timeout'))->toBe(10);
});

it('wraps the Resend transport with the circuit breaker', function (): void {
    config(['services.resend.key' => 're_test']);

    $mailer = Mail::mailer('resend');

    expect($mailer->getSymfonyTransport())->toBeInstanceOf(CircuitBreakerTransport::class);
});

it('builds the Resend client with bounded Guzzle timeouts', function (): void {
    config(['services.resend.key' => 're_test']);

    $client = app(ResendClientContract::class);
    $transporter = (new ReflectionProperty(ResendClient::class, 'transporter'))->getValue($client);
    $guzzle = (new ReflectionProperty($transporter::class, 'client'))->getValue($transporter);

    expect($guzzle)->toBeInstanceOf(GuzzleClient::class)
        ->and($guzzle->getConfig('timeout'))->toBe(10)
        ->and($guzzle->getConfig('connect_timeout'))->toBe(5);
});
