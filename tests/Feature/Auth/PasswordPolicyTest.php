<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/*
 * The product policy is 8–15 characters. These tests pin BOTH edges on every
 * write path Fortify exposes: invitation activation, reset and authenticated
 * update. The password is hashed with Argon2id; the tests only assert the
 * boundary, never the hash format (that is config/hashing.php's job).
 */
it('does not expose public self-registration', function (): void {
    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => '123456',
        'password_confirmation' => '123456',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
});

it('enforces the 8 to 15 character policy on invitation activation', function (string $password, bool $accepted): void {
    $user = User::factory()->invited()->create();

    $url = URL::temporarySignedRoute(
        'users.activation.store',
        now()->addHour(),
        [
            'user' => $user->uuid,
            'invitation' => $user->invitation_sent_at?->getTimestamp(),
        ],
    );

    $response = $this->post($url, [
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    if ($accepted) {
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        expect(Hash::check($password, (string) $user->refresh()->password))->toBeTrue()
            ->and($user->status->value)->toBe('active');

        return;
    }

    $response->assertSessionHasErrors('password');
    $this->assertGuest();

    expect($user->refresh()->status->value)->toBe('invited');
})->with([
    'seven characters' => ['1234567', false],
    'eight characters' => ['12345678', true],
    'fifteen characters' => ['123456789012345', true],
    'sixteen characters' => ['1234567890123456', false],
]);

it('does not apply the new-password ceiling to sign in', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('1234567890123456'),
    ]);

    // A legacy credential longer than the current policy must still be able to
    // authenticate: the policy governs NEW passwords, not Hash::check.
    $this->post('/login', [
        'email' => $user->email,
        'password' => '1234567890123456',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('enforces the 8 to 15 character policy on password reset', function (string $password, bool $accepted): void {
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
    'seven characters' => ['1234567', false],
    'eight characters' => ['12345678', true],
    'sixteen characters' => ['1234567890123456', false],
]);

it('enforces the 8 to 15 character policy on an authenticated password update', function (string $password, bool $accepted): void {
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
    'seven characters' => ['1234567', false],
    'eight characters' => ['12345678', true],
    'sixteen characters' => ['1234567890123456', false],
]);

it('rejects a password update with the wrong current password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current1'),
    ]);

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'wrong-one',
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(Hash::check('current1', $user->refresh()->password))->toBeTrue();
});
