<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Fortify owns the endpoints; these tests prove the Inertia views are wired
 * and that the recovery flow actually sends a link and changes the password.
 */
it('renders the forgot-password screen', function (): void {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

it('emails a reset link to a known address', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the reset screen with the token from the link', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('email', $user->email)
            ->where('token', $token));
});

it('resets the password and queues the security alert', function (): void {
    Notification::fake();
    Queue::fake();

    $user = User::factory()->create([
        'password' => Hash::make('oldpass1'),
    ]);
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpass1',
        'password_confirmation' => 'newpass1',
    ])->assertRedirect('/login');

    expect(Hash::check('newpass1', $user->refresh()->password))->toBeTrue();

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::PasswordChanged,
    );
});
