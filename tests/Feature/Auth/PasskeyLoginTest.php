<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\AccountLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

uses(RefreshDatabase::class);

/**
 * A passkey row with its owner already resolved, so the authorization gate can
 * be exercised without a real WebAuthn assertion.
 */
function passkeyFor(User $user): Passkey
{
    $passkey = new Passkey;
    $passkey->user_id = $user->getKey();
    $passkey->setRelation('user', $user);

    return $passkey;
}

it('serves a passkey challenge to a guest', function (): void {
    $this->getJson('/passkeys/login/options')
        ->assertOk()
        ->assertJsonStructure([
            'options' => ['challenge', 'rpId', 'timeout', 'userVerification'],
        ]);
});

it('refuses a malformed credential', function (): void {
    $this->postJson('/passkeys/login', ['credential' => ['id' => 'abc']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credential.rawId');
});

it('keeps a signed-in user away from the guest ceremony', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/passkeys/login/options')
        ->assertRedirect();
});

it('implements the passkey user contract', function (): void {
    expect(User::factory()->create())->toBeInstanceOf(PasskeyUser::class);
});

it('refuses a passkey for a locked account', function (): void {
    $user = User::factory()->create(['locked_until' => now()->addHour()]);

    expect(fn () => Passkeys::allowsLogin(request(), passkeyFor($user)))
        ->toThrow(ValidationException::class, AccountLockout::message($user));
});

it('refuses a passkey for an unverified account', function (): void {
    $user = User::factory()->unverified()->create();

    expect(fn () => Passkeys::allowsLogin(request(), passkeyFor($user)))
        ->toThrow(ValidationException::class);
});

it('allows a passkey for a verified, unlocked account', function (): void {
    $user = User::factory()->create();

    expect(Passkeys::allowsLogin(request(), passkeyFor($user)))->toBeTrue();
});
