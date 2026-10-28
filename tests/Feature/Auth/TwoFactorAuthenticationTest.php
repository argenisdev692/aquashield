<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function confirmedTwoFactorUser(): User
{
    $user = User::factory()->create();

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    return $user->refresh();
}

function currentTotpCode(User $user): string
{
    /** @var string $secret */
    $secret = Fortify::currentEncrypter()->decrypt($user->refresh()->two_factor_secret);

    /** @var string $code */
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    return $code;
}

it('refuses a JSON two-factor enable from a guest with 401', function (): void {
    $this->postJson('/user/two-factor-authentication')->assertUnauthorized();
});

it('enables two-factor, serves the QR and confirms a valid TOTP', function (): void {
    $user = User::factory()->create();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/settings/profile')
        ->post('/user/two-factor-authentication')
        ->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->getJson('/user/two-factor-qr-code')
        ->assertOk()
        ->assertJsonStructure(['svg', 'url']);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->from('/settings/profile')
        ->post('/user/confirmed-two-factor-authentication', [
            'code' => currentTotpCode($user),
        ])
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->getJson('/user/two-factor-recovery-codes')
        ->assertOk()
        ->assertJsonCount(8);
});

it('rejects an invalid confirmation code with 422', function (): void {
    $user = User::factory()->create();
    $user->refresh();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication');

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])
        ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

it('challenges a password login when two-factor is confirmed', function (): void {
    $user = confirmedTwoFactorUser();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/two-factor-challenge');

    $this->assertGuest();

    $this->post('/two-factor-challenge', [
        'code' => currentTotpCode($user),
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('refuses the challenge with a wrong code', function (): void {
    $user = confirmedTwoFactorUser();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post('/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('consumes a recovery code and replaces it', function (): void {
    $user = confirmedTwoFactorUser();
    $recoveryCode = $user->recoveryCodes()[0];

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->recoveryCodes())->not->toContain($recoveryCode);
});

it('disables two-factor and wipes the secret', function (): void {
    $user = confirmedTwoFactorUser();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from('/settings/profile')
        ->delete('/user/two-factor-authentication')
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});
