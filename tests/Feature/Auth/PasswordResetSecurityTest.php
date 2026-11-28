<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Jobs\SendSecurityAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Passkeys\Passkey;

uses(RefreshDatabase::class);

/**
 * A password reset is the recovery path for a compromised credential. Anything
 * issued with the old password must die with it.
 */
it('revokes every session and API token after a successful password reset', function (): void {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    $user->createToken('api');

    DB::table('sessions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);

    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpass1',
        'password_confirmation' => 'newpass1',
    ])->assertRedirect('/login');

    expect(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);
});

it('revokes passkeys and disables two-factor after a password reset', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $passkey = new Passkey;
    $passkey->user_id = $user->getKey();
    $passkey->name = 'Phone';
    $passkey->credential_id = (string) Str::uuid();
    $passkey->credential = ['aaguid' => '00000000-0000-0000-0000-000000000000'];
    $passkey->save();

    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpass1',
        'password_confirmation' => 'newpass1',
    ])->assertRedirect('/login');

    $user->refresh();

    expect($user->passkeys()->count())->toBe(0)
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    Queue::assertPushed(
        SendSecurityAlert::class,
        fn (SendSecurityAlert $job): bool => $job->event === SecurityEvent::FactorsRevoked,
    );

    expect(DB::table('activity_log')
        ->where('event', 'auth.factors.revoked')
        ->where('subject_id', $user->getKey())
        ->exists())->toBeTrue();
});
