<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

/**
 * Five wrong passwords lock the account for an hour, email the owner through
 * the queue, and the lock applies to both the session and the token transport.
 */
it('locks the account for an hour after five failed password attempts', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_until)->not->toBeNull()
        ->and($user->locked_until?->isFuture())->toBeTrue();

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::AccountLocked
            && $job->email === $user->email
            && ($job->context['failed_attempts'] ?? null) === '5'
            && ($job->context['locked_until'] ?? null) !== null,
    );
});

it('rejects the correct password while the lock is active', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'locked_until' => now()->addMinutes(45),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertSessionHasErrors('email');

    /** @var ViewErrorBag $errors */
    $errors = session('errors');

    expect($errors->getBag('default')->first('email'))
        ->toContain('Too many failed sign-in attempts')
        ->toContain('45 minutes');

    $this->assertGuest();
});

it('lets the account back in after the lock expires and clears the state', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'failed_login_attempts' => 4,
        'locked_until' => now()->subMinute(),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull();
});

it('resets the failure counter on a successful login', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'failed_login_attempts' => 3,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertRedirect('/dashboard');

    expect($user->refresh()->failed_login_attempts)->toBe(0);
});

it('applies the same lock to the token endpoint', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'pest',
        ])->assertUnprocessable();
    }

    expect($user->refresh()->locked_until?->isFuture())->toBeTrue();

    // Step past the 5/min route limiter without leaving the 60-minute lock.
    $this->travel(61)->seconds();

    $locked = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
        'device_name' => 'pest',
    ])->assertUnprocessable();

    expect($locked->json('errors.email.0'))->toContain('Too many failed sign-in attempts');
});
