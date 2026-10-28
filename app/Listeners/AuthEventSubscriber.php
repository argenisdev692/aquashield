<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use App\Support\AuthRequestContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * One subscriber for every authentication event: audit first, then the queued
 * security email where the change is material.
 *
 * It never logs credentials: the framework's Failed event carries the whole
 * submitted payload (including the password), so only the email is copied into
 * the activity properties.
 */
final readonly class AuthEventSubscriber
{
    public function __construct(
        private AuditPort $auditPort,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Logout::class, [self::class, 'handleLogout']);
        $events->listen(Failed::class, [self::class, 'handleFailed']);
        $events->listen(TwoFactorAuthenticationEnabled::class, [self::class, 'handleTwoFactorEnabled']);
        $events->listen(TwoFactorAuthenticationConfirmed::class, [self::class, 'handleTwoFactorConfirmed']);
        $events->listen(TwoFactorAuthenticationDisabled::class, [self::class, 'handleTwoFactorDisabled']);
        $events->listen(RecoveryCodesGenerated::class, [self::class, 'handleRecoveryCodesGenerated']);
        $events->listen(RecoveryCodeReplaced::class, [self::class, 'handleRecoveryCodeReplaced']);
        $events->listen(TwoFactorAuthenticationFailed::class, [self::class, 'handleTwoFactorFailed']);
        $events->listen(ValidTwoFactorAuthenticationCodeProvided::class, [self::class, 'handleValidTwoFactorCode']);
    }

    public function handleLogin(Login $event): void
    {
        $this->log('auth.login', $event->user instanceof User ? $event->user : null, [
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $this->log('auth.logout', $event->user instanceof User ? $event->user : null, [
            'guard' => $event->guard,
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->log('auth.failed', $event->user instanceof User ? $event->user : null, [
            'guard' => $event->guard,
            'email' => is_string($email) ? $email : null,
        ], null);
    }

    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->log('auth.2fa.enabled', $event->user);
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->log('auth.2fa.confirmed', $event->user);
        $this->alert($event->user, SecurityEvent::TwoFactorEnabled);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->log('auth.2fa.disabled', $event->user);
        $this->alert($event->user, SecurityEvent::TwoFactorDisabled);
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->log('auth.2fa.recovery_codes_generated', $event->user);
        $this->alert($event->user, SecurityEvent::RecoveryCodesRegenerated);
    }

    public function handleRecoveryCodeReplaced(RecoveryCodeReplaced $event): void
    {
        if ($event->user instanceof User) {
            $this->log('auth.2fa.recovery_code_used', $event->user);
        }
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->log('auth.2fa.failed', $event->user);
    }

    public function handleValidTwoFactorCode(ValidTwoFactorAuthenticationCodeProvided $event): void
    {
        $this->log('auth.2fa.passed', $event->user);
    }

    /**
     * @param array<string, scalar|null> $properties
     */
    private function log(
        string $event,
        ?User $user,
        array $properties = [],
        ?User $subject = null,
    ): void {
        try {
            $this->auditPort->log(
                $event,
                $subject ?? $user,
                [...$properties, ...AuthRequestContext::properties()],
                $user,
                'auth',
            );
        } catch (Throwable $exception) {
            // A failed audit write must not block a login or a credential
            // change; report it for operators instead.
            report($exception);
        }
    }

    private function alert(User $user, SecurityEvent $event): void
    {
        try {
            SendSecurityAlert::forUser($user, $event, AuthRequestContext::properties());
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
