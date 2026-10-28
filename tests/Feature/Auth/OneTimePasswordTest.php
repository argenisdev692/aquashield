<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function issuedOtpCode(User $user): string
{
    return (string) $user->oneTimePasswords()->latest('id')->value('password');
}

function otpUserTotpCode(User $user): string
{
    /** @var string $secret */
    $secret = Fortify::currentEncrypter()->decrypt($user->refresh()->two_factor_secret);

    /** @var string $code */
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    return $code;
}

it('renders the email code screen for a guest', function (): void {
    $this->get('/login/otp')->assertInertia(
        fn (Assert $page) => $page
            ->component('Auth/OtpLogin')
            ->where('email', null),
    );
});

it('sends a six-digit code to a known address', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/login/otp', ['email' => $user->email])
        ->assertRedirect('/login/otp')
        ->assertSessionHas('otp.email', $user->email);

    Notification::assertSentTo($user, OneTimePasswordNotification::class);
    expect($user->oneTimePasswords()->count())->toBe(1)
        ->and(issuedOtpCode($user))->toMatch('/^\d{6}$/');
});

it('answers identically for an unknown address and sends nothing', function (): void {
    Notification::fake();

    $this->post('/login/otp', ['email' => 'nobody@example.com'])
        ->assertRedirect('/login/otp')
        ->assertSessionHas('otp.email', 'nobody@example.com')
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('signs in with a valid email code and regenerates the session', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $this->post('/login/otp', ['email' => $user->email]);

    $this->post('/login/otp/verify', ['code' => issuedOtpCode($user)])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->oneTimePasswords()->count())->toBe(0);
});

it('marks the email verified when a valid code proves ownership', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $this->post('/login/otp', ['email' => $user->email]);

    $this->post('/login/otp/verify', ['code' => issuedOtpCode($user)])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('refuses a wrong email code', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $this->post('/login/otp', ['email' => $user->email]);

    $this->post('/login/otp/verify', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('refuses an expired email code', function (): void {
    $user = User::factory()->create();
    $expired = $user->createOneTimePassword(-1);

    $this->withSession(['otp.email' => $user->email])
        ->post('/login/otp/verify', ['code' => $expired->password])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('does not let the email code bypass a confirmed TOTP factor', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login/otp', ['email' => $user->email]);
    $this->post('/login/otp/verify', ['code' => issuedOtpCode($user)])
        ->assertRedirect('/two-factor-challenge');

    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => otpUserTotpCode($user)])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('consumes an email code only once', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $this->post('/login/otp', ['email' => $user->email]);
    $code = issuedOtpCode($user);

    $this->post('/login/otp/verify', ['code' => $code])->assertRedirect('/dashboard');
    $this->post('/logout');

    $this->withSession(['otp.email' => $user->email])
        ->post('/login/otp/verify', ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('requires the send step before verifying', function (): void {
    $this->post('/login/otp/verify', ['code' => '123456'])
        ->assertRedirect('/login/otp')
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('throttles repeated code requests by email and IP', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->post('/login/otp', ['email' => $user->email])
            ->assertRedirect('/login/otp');
    }

    $this->post('/login/otp', ['email' => $user->email])->assertStatus(429);
});

it('throttles repeated verification attempts by session and IP', function (): void {
    $user = User::factory()->create();

    $this->withSession(['otp.email' => $user->email]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login/otp/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $this->post('/login/otp/verify', ['code' => '000000'])->assertStatus(429);
});

it('supports the JSON email-code flow used by the login tabs', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/login/otp', ['email' => $user->email])
        ->assertStatus(202)
        ->assertJsonPath('email', $user->email);

    $this->postJson('/login/otp/verify', ['code' => issuedOtpCode($user)])
        ->assertOk()
        ->assertJsonPath('redirect', '/dashboard')
        ->assertJsonPath('two_factor', false);

    $this->assertAuthenticatedAs($user);
});

it('returns a challenge redirect in the JSON flow when TOTP is enabled', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->postJson('/login/otp', ['email' => $user->email])->assertStatus(202);

    $this->postJson('/login/otp/verify', ['code' => issuedOtpCode($user)])
        ->assertOk()
        ->assertJsonPath('two_factor', true)
        ->assertJsonPath('redirect', route('two-factor.login'));

    $this->assertGuest();
});
