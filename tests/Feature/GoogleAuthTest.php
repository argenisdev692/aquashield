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

test('a verified google identity creates and authenticates a user', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-7', 'email' => 'ada@example.com', 'name' => 'Ada Lovelace'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'ada@example.com',
        'google_id' => 'google-7',
        'name' => 'Ada Lovelace',
    ]);
});

test('a verified google identity links an existing password account', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    Socialite::shouldReceive('driver->user')->andReturn(
        SocialiteUser::fake(['id' => 'google-8', 'email' => 'ada@example.com'])
            ->setRaw(['email_verified' => true]),
    );

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    expect($user->refresh()->google_id)->toBe('google-8');
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
