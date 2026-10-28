<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function authenticatorCode(User $user): string
{
    /** @var string $secret */
    $secret = Fortify::currentEncrypter()->decrypt($user->refresh()->two_factor_secret);

    /** @var string $code */
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    return $code;
}

it('signs in with email plus a confirmed authenticator code', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login/authenticator', [
        'email' => $user->email,
        'code' => authenticatorCode($user),
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('refuses an invalid authenticator code', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login/authenticator', [
        'email' => $user->email,
        'code' => '000000',
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('refuses authenticator login until the email is verified', function (): void {
    $user = User::factory()->unverified()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login/authenticator', [
        'email' => $user->email,
        'code' => authenticatorCode($user),
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses authenticator login when the account has not enabled it', function (): void {
    $user = User::factory()->create();

    $this->post('/login/authenticator', [
        'email' => $user->email,
        'code' => '123456',
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('throttles authenticator login attempts', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login/authenticator', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertSessionHasErrors('code');
    }

    $this->post('/login/authenticator', [
        'email' => $user->email,
        'code' => '000000',
    ])->assertStatus(429);
});
