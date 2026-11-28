<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
            ->where('user.first_name', 'Ada')
            ->where('user.last_name', 'Lovelace')
            ->where('user.name', 'Ada Lovelace')
            ->where('user.email', 'ada@example.com')
            ->where('user.email_verified', true)
            ->where('user.registered_by_id', null)
            ->where('user.profile_photo_url', null)
            ->where('twoFactorEnabled', false)
            ->where('twoFactorConfirmed', false)
            ->has('googleMapsApiKey')
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
            'email' => 'ada.king@gmail.com',
            'current_password' => 'password',
        ])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'profile-information-updated');

    $user->refresh();

    expect($user->name)->toBe('Ada King')
        ->and($user->first_name)->toBe('Ada')
        ->and($user->last_name)->toBe('King')
        ->and($user->email)->toBe('ada.king@gmail.com');
});

it('updates the canonical profile fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'username' => 'ghopper',
            'email' => 'grace@gmail.com',
            'current_password' => 'password',
            'phone' => '+15555550100',
            'date_of_birth' => '1906-12-09',
            'address' => '123 Main St',
            'address_2' => 'Apt 4',
            'zip_code' => '33101',
            'city' => 'Miami',
            'state' => 'FL',
            'country' => 'United States',
            'gender' => 'female',
            'latitude' => 25.7743,
            'longitude' => -80.1937,
            'terms_and_conditions' => true,
        ])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'profile-information-updated');

    $user->refresh();

    expect($user->first_name)->toBe('Grace')
        ->and($user->last_name)->toBe('Hopper')
        ->and($user->username)->toBe('ghopper')
        ->and($user->email)->toBe('grace@gmail.com')
        ->and($user->phone)->toBe('+15555550100')
        ->and($user->date_of_birth)->toBe('1906-12-09')
        ->and($user->address)->toBe('123 Main St')
        ->and($user->address_2)->toBe('Apt 4')
        ->and($user->zip_code)->toBe('33101')
        ->and($user->city)->toBe('Miami')
        ->and($user->state)->toBe('FL')
        ->and($user->country)->toBe('United States')
        ->and($user->gender)->toBe('female')
        ->and($user->latitude)->toBe(25.7743)
        ->and($user->longitude)->toBe(-80.1937)
        ->and($user->terms_and_conditions)->toBeTrue();
});

it('requires the current password to change the email', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada',
            'email' => 'attacker@gmail.com',
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');

    expect($user->refresh()->email)->toBe('ada@example.com');
});

it('rejects an email change with the wrong current password', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada',
            'email' => 'attacker@gmail.com',
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');

    expect($user->refresh()->email)->toBe('ada@example.com');
});

it('audits an email change and alerts the old address', function (): void {
    Queue::fake();

    $user = User::factory()->create(['email' => 'old@gmail.com']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada',
            'email' => 'new@gmail.com',
            'current_password' => 'password',
        ])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->email === 'old@gmail.com'
            && $job->event === SecurityEvent::EmailChanged
            && ($job->context['new_email'] ?? null) === 'new@gmail.com',
    );

    expect(DB::table('activity_log')
        ->where('event', 'user.email.changed')
        ->where('subject_id', $user->getKey())
        ->exists())->toBeTrue();
});

it('does not require the current password when the email is unchanged', function (): void {
    $user = User::factory()->create(['email' => 'ada@gmail.com']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada Updated',
            'email' => 'ada@gmail.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->first_name)->toBe('Ada Updated');
});

it('ignores fields outside the profile allowlist', function (): void {
    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/original.webp',
        'google_id' => 'google-original',
    ]);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada',
            'email' => $user->email,
            'profile_photo_path' => 'profile-photos/evil.webp',
            'google_id' => 'google-evil',
            'two_factor_secret' => 'evil-secret',
        ])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->profile_photo_path)->toBe('profile-photos/original.webp')
        ->and($user->google_id)->toBe('google-original')
        ->and($user->two_factor_secret)->toBeNull();
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

it('rejects an invalid username and out-of-range coordinates', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada',
            'email' => $user->email,
            'username' => 'not a username',
            'latitude' => 120,
            'longitude' => 200,
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', [
            'username',
            'latitude',
            'longitude',
        ]);
});

it('rejects a username already owned by another user', function (): void {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'first_name' => 'Ada',
            'email' => $user->email,
            'username' => 'taken',
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'username');
});

it('rejects a profile email already owned by another user', function (): void {
    $owner = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->put('/user/profile-information', [
            'name' => 'Ada Lovelace',
            'email' => $owner->email,
            'current_password' => 'password',
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
