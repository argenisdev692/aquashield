<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

test('the google entry point redirects to google', function () {
    $this->get('/auth/google')->assertRedirectContains('accounts.google.com');
});

test('a verified google identity links an existing verified password account', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-8', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $user->refresh();

    expect($user->google_id)->toBe('google-8')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('a verified google identity is refused for an unverified local account', function () {
    $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-8', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();

    $user->refresh();

    expect($user->google_id)->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
});

test('a google account never seizes an address linked to another google id', function () {
    User::factory()->create(['email' => 'ada@example.com', 'google_id' => 'google-owner']);

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-intruder', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});

test('an unverified google email is refused', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-9', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => false]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
});

test('a callback without a valid state is refused', function () {
    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});

test('a google identity with two factor is sent to the challenge', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'google_id' => 'google-2fa',
    ]);

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-2fa', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
});

test('an unknown google identity is refused and never creates an account', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-unknown', 'email' => 'new-person@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'new-person@example.com']);
});

test('a locked google account is refused', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'google_id' => 'google-locked',
        'locked_until' => now()->addHour(),
    ]);

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-locked', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});

test('a suspended google account is refused', function () {
    User::factory()->suspended()->create([
        'email' => 'ada@example.com',
        'google_id' => 'google-suspended',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-suspended', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});
