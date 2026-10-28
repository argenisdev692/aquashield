<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/*
 * The product policy is 6–10 characters. These tests pin BOTH edges on every
 * write path Fortify exposes: registration, reset and authenticated update.
 * The password is hashed with Argon2id; the tests only assert the boundary,
 * never the hash format (that is config/hashing.php's job).
 */
it('enforces the 6 to 10 character policy on registration', function (string $password, bool $accepted): void {
    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    if ($accepted) {
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        return;
    }

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
})->with([
    'five characters' => ['12345', false],
    'six characters' => ['123456', true],
    'ten characters' => ['1234567890', true],
    'eleven characters' => ['12345678901', false],
]);

it('does not apply the new-password ceiling to sign in', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('123456789012'),
    ]);

    // A legacy credential longer than the current policy must still be able to
    // authenticate: the policy governs NEW passwords, not Hash::check.
    $this->post('/login', [
        'email' => $user->email,
        'password' => '123456789012',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('enforces the 6 to 10 character policy on password reset', function (string $password, bool $accepted): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    if ($accepted) {
        $response->assertRedirect('/login');
        expect(Hash::check($password, $user->refresh()->password))->toBeTrue();

        return;
    }

    $response->assertSessionHasErrors('password');
    expect(Hash::check($password, $user->refresh()->password))->toBeFalse();
})->with([
    'five characters' => ['12345', false],
    'six characters' => ['123456', true],
    'eleven characters' => ['12345678901', false],
]);

it('enforces the 6 to 10 character policy on an authenticated password update', function (string $password, bool $accepted): void {
    $user = User::factory()->create([
        'password' => Hash::make('current1'),
    ]);

    $response = $this->actingAs($user)->put('/user/password', [
        'current_password' => 'current1',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    if ($accepted) {
        $response->assertSessionHasNoErrors();
        expect(Hash::check($password, $user->refresh()->password))->toBeTrue();

        return;
    }

    $response->assertSessionHasErrorsIn('updatePassword', 'password');
    expect(Hash::check($password, $user->refresh()->password))->toBeFalse();
})->with([
    'five characters' => ['12345', false],
    'six characters' => ['123456', true],
    'eleven characters' => ['12345678901', false],
]);

it('rejects a password update with the wrong current password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current1'),
    ]);

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'wrong-one',
        'password' => '123456',
        'password_confirmation' => '123456',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(Hash::check('current1', $user->refresh()->password))->toBeTrue();
});
