<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
