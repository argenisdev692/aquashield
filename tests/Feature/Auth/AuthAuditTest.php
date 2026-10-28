<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function auditTotpCode(User $user): string
{
    /** @var string $secret */
    $secret = Fortify::currentEncrypter()->decrypt($user->refresh()->two_factor_secret);

    /** @var string $code */
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    return $code;
}

it('audits a successful web login with request context', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $activity = Activity::query()->where('event', 'auth.login')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->causer_id)->toBe($user->id)
        ->and($activity?->properties?->get('ip_address'))->toBe('127.0.0.1');
});

it('audits a failed login without persisting the password', function (): void {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    $activity = Activity::query()->where('event', 'auth.failed')->first();
    $properties = $activity?->properties?->toArray() ?? [];

    expect($activity)->not->toBeNull()
        ->and($properties['email'] ?? null)->toBe($user->email)
        ->and($properties)->not->toHaveKey('password');
});

it('queues a security email and writes an audit row on password change', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'password' => Hash::make('current1'),
    ]);

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'current1',
        'password' => '123456',
        'password_confirmation' => '123456',
    ])->assertSessionHasNoErrors();

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::PasswordChanged,
    );

    expect(Activity::query()->where('event', 'auth.password.changed')->exists())->toBeTrue();
});

it('queues the two-factor enabled email on confirmation', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/confirmed-two-factor-authentication', [
            'code' => auditTotpCode($user),
        ])->assertRedirect();

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::TwoFactorEnabled,
    );

    expect(Activity::query()->where('event', 'auth.2fa.confirmed')->exists())->toBeTrue();
});

it('audits logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(Activity::query()->where('event', 'auth.logout')->exists())->toBeTrue();
});
