<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Mail\SecurityAlertMail;
use Mockery\MockInterface;
use Shared\Infrastructure\Mail\MailInterface;
use Tests\TestCase;

uses(TestCase::class);

it('sends the corporate security mailable through the mail port', function (): void {
    $this->mock(MailInterface::class, function (MockInterface $mail): void {
        $mail->shouldReceive('send')
            ->once()
            ->with(
                'ada@example.com',
                Mockery::on(fn (mixed $mailable): bool => $mailable instanceof SecurityAlertMail
                    && $mailable->event === SecurityEvent::TwoFactorDisabled
                    && $mailable->recipientName === 'Ada Lovelace'),
            );
    });

    (new SendSecurityAlert(
        email: 'ada@example.com',
        name: 'Ada Lovelace',
        event: SecurityEvent::TwoFactorDisabled,
        context: ['ip_address' => '127.0.0.1'],
    ))->handle(app(MailInterface::class));
});
