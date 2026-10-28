<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function apiTotpCode(User $user): string
{
    /** @var string $secret */
    $secret = Fortify::currentEncrypter()->decrypt($user->refresh()->two_factor_secret);

    /** @var string $code */
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    return $code;
}

function apiTwoFactorUser(): User
{
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    return $user->refresh();
}

it('issues a Sanctum token and an allowlisted user payload', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iphone',
    ])->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_at',
            'user' => [
                'uuid',
                'first_name',
                'last_name',
                'name',
                'username',
                'email',
                'email_verified',
                'registered_by_id',
                'profile_photo_url',
                'two_factor_enabled',
                'permissions',
            ],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', $user->email);

    expect($response->json('token'))->toBeString()->not->toBe('')
        ->and($response->json('user.email_verified'))->toBeTrue()
        ->and($response->json('user.registered_by_id'))->toBeNull()
        ->and($response->json('user.profile_photo_url'))->toBeNull()
        ->and($response->json('user.two_factor_enabled'))->toBeFalse();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'iphone',
    ]);
});

it('rejects invalid API credentials with 422', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('rejects a missing or malformed API payload with 422', function (): void {
    $this->postJson('/api/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('refuses an unverified email on API login', function (): void {
    $user = User::factory()->unverified()->create([
        'password' => Hash::make('password'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('requires a TOTP code when two-factor is confirmed', function (): void {
    $user = apiTwoFactorUser();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('two_factor_code');

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'two_factor_code' => apiTotpCode($user),
    ])->assertOk()
        ->assertJsonPath('user.two_factor_enabled', true);
});

it('returns the current user for a bearer token and 401 without one', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $token = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('token');

    $this->getJson('/api/auth/me')->assertUnauthorized();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('uuid', $user->uuid);
});

it('rotates the bearer token on refresh and invalidates the old one', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $old = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('token');

    $new = $this->withToken($old)
        ->postJson('/api/auth/refresh')
        ->assertOk()
        ->json('token');

    expect($new)->toBeString()->not->toBe($old);

    Auth::forgetGuards();

    $this->withToken($old)->getJson('/api/auth/me')->assertUnauthorized();
    $this->withToken($new)->getJson('/api/auth/me')->assertOk();
});

it('revokes the current token on logout', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $token = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('token');

    $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);

    // The test application reuses the guard instance between requests; a real
    // HTTP request resolves a fresh guard. Forget it before proving the token
    // is dead.
    Auth::forgetGuards();

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('throttles repeated API login attempts', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
