<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('refuses a password login for a suspended account', function (): void {
    $user = User::factory()->suspended()->create(['email' => 'suspended@gmail.com']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses a password login for an invited account', function (): void {
    $user = User::factory()->invited()->create([
        'email' => 'invited@gmail.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('terminates an existing web session when the account is suspended', function (): void {
    $user = User::factory()->suspended()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('revokes API tokens when an administrator suspends an account', function (): void {
    $target = User::factory()->create();
    $target->createToken('mobile');

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$target->uuid],
            'status' => 'suspended',
        ])
        ->assertOk();

    expect($target->refresh()->isSuspended())->toBeTrue()
        ->and($target->tokens()->count())->toBe(0);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects an API login for a suspended account', function (): void {
    $user = User::factory()->suspended()->create(['email' => 'api-suspended@gmail.com']);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'test',
    ])->assertUnprocessable();
});

it('rejects an existing API token once the account is suspended', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $user->forceFill(['status' => 'suspended'])->save();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertForbidden();
});

it('forces a temporary-password account to the password tab and clears the flag after the change', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/settings/profile#password');

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk();

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'password',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])
        ->assertRedirect();

    expect($user->refresh()->must_change_password)->toBeFalse();
});

it('reports the active session count only through the auth surface', function (): void {
    config(['session.driver' => 'database']);

    // The guard test above deletes database sessions; this pins that the
    // suspension path does not touch an unrelated user's rows.
    $user = User::factory()->create();
    $other = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => $user->getKey(),
        'payload' => 'payload',
        'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$user->uuid],
            'status' => 'suspended',
        ])
        ->assertOk();

    expect(DB::table('sessions')->where('user_id', $user->getKey())->count())->toBe(0)
        ->and($other->refresh()->isActive())->toBeTrue();
});
