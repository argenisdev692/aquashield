<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('redirects a guest from the account screen', function (): void {
    $this->get('/settings/profile')->assertRedirect('/login');
});

it('renders the account screen with an allowlisted user shape', function (): void {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    // The auth guard loads every column; strict mode makes a fresh factory
    // model (which only has the attributes it set) fail on unloaded ones.
    $user->refresh();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Profile')
            ->where('user.name', 'Ada Lovelace')
            ->where('user.email', 'ada@example.com')
            ->where('twoFactorEnabled', false)
            ->where('twoFactorConfirmed', false)
            ->missing('user.password')
            ->missing('user.remember_token'));
});

it('refuses a JSON profile write from a guest with 401', function (): void {
    $this->putJson('/user/profile-information', [
        'name' => 'Anonymous',
        'email' => 'anonymous@example.com',
    ])->assertUnauthorized();
});

it('updates the authenticated user profile', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'name' => 'Ada King',
            'email' => 'ada.king@example.com',
        ])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'profile-information-updated');

    $user->refresh();

    expect($user->name)->toBe('Ada King')
        ->and($user->email)->toBe('ada.king@example.com');
});

it('rejects an invalid profile payload with 422', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['name', 'email']);
});

it('rejects a profile email already owned by another user', function (): void {
    $owner = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'name' => 'Ada Lovelace',
            'email' => $owner->email,
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'email');
});

it('refuses a JSON password write from a guest with 401', function (): void {
    $this->putJson('/user/password', [
        'current_password' => 'password',
        'password' => '123456',
        'password_confirmation' => '123456',
    ])->assertUnauthorized();
});
