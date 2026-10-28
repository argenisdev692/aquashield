<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Mail\SecurityAlertMail;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Infrastructure\Branding\BrandPalette;
use Spatie\OneTimePasswords\Models\OneTimePassword;

uses(RefreshDatabase::class);

it('renders the shared corporate layout for a security alert', function (): void {
    $html = (new SecurityAlertMail(
        recipientName: 'Ada Lovelace',
        event: SecurityEvent::PasswordChanged,
        context: [
            'ip_address' => '203.0.113.10',
            'location' => 'Madrid, Community of Madrid, Spain',
            'occurred_at' => '2026-10-28T12:00:00+00:00',
        ],
    ))->render();

    expect($html)
        ->toContain('logo-email.png')
        ->toContain(BrandPalette::PRIMARY_ACCENT)
        ->toContain('Password changed')
        ->toContain('Review security settings')
        ->toContain('203.0.113.10')
        ->toContain('Approximate location')
        ->toContain('Madrid, Community of Madrid, Spain')
        ->toContain('Hi Ada Lovelace');
});

it('renders the lockout alert with attempts, location and a reset link', function (): void {
    $html = (new SecurityAlertMail(
        recipientName: 'Ada Lovelace',
        event: SecurityEvent::AccountLocked,
        context: [
            'ip_address' => '203.0.113.10',
            'location' => 'Madrid, Spain',
            'failed_attempts' => '5',
            'locked_until' => '2026-10-28T13:00:00+00:00',
        ],
    ))->render();

    expect($html)
        ->toContain('Sign-in temporarily locked')
        ->toContain('Failed attempts')
        ->toContain('Madrid, Spain')
        ->toContain('Reset your password');
});

it('renders the one-time-code email from the same layout', function (): void {
    $html = view('emails.otp', [
        'recipientName' => 'Ada Lovelace',
        'code' => '428913',
        'expiresInMinutes' => 2,
    ])->render();

    expect($html)
        ->toContain('logo-email.png')
        ->toContain('428913')
        ->toContain('expires in 2 minutes');
});

it('keeps the OTP notification on the corporate view', function (): void {
    $user = User::factory()->make(['name' => 'Ada Lovelace']);
    $oneTimePassword = new OneTimePassword(['password' => '428913']);

    $message = (new OneTimePasswordNotification($oneTimePassword))->toMail($user);

    expect($message->view)->toBe('emails.otp')
        ->and($message->viewData['code'] ?? null)->toBe('428913')
        ->and($message->viewData['recipientName'] ?? null)->toBe('Ada Lovelace');
});
