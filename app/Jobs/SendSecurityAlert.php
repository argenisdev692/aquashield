<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SecurityEvent;
use App\Mail\SecurityAlertMail;
use App\Models\User;
use App\Support\ClientLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shared\Infrastructure\Mail\MailInterface;

/**
 * Queued corporate security email.
 *
 * The worker calls the Shared MailInterface, so the provider is a config
 * switch (Resend today), never a hard-coded SDK in the job. Failures retry
 * with the queue's normal failed-job bookkeeping; the request that changed the
 * credential never waits for Resend.
 */
final class SendSecurityAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param array<string, string|null> $context
     */
    public function __construct(
        public string $email,
        public string $name,
        public SecurityEvent $event,
        public array $context = [],
    ) {}

    /**
     * @param array<string, string|null> $context
     */
    public static function forUser(User $user, SecurityEvent $event, array $context = []): void
    {
        self::dispatch($user->email, $user->name, $event, $context);
    }

    public function handle(MailInterface $mail, ?ClientLocation $location = null): void
    {
        $location ??= app(ClientLocation::class);

        $context = [
            ...$this->context,
            'occurred_at' => now()->toIso8601String(),
        ];

        // Resolve the label in the worker, not in the login request: a slow
        // geolocation provider must never add latency to an authentication
        // response. The IP is already in the context; private ranges resolve
        // to null and the email still goes out.
        if (! array_key_exists('location', $context)) {
            $context['location'] = $location->forIp($context['ip_address'] ?? null);
        }

        $mail->send($this->email, new SecurityAlertMail(
            recipientName: $this->name,
            event: $this->event,
            context: $context,
        ));
    }
}
