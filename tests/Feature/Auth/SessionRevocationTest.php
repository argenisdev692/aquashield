<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('logs out a web session that carries a stale password hash', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['password_hash_web' => 'stale-password-hash'])
        ->get('/settings/profile')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('deletes every API token when the password changes', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
    $user->createToken('phone');
    $user->createToken('tablet');

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0);
});

it('keeps the session that made the password change', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
        ->assertSessionHasNoErrors();

    $this->get('/settings/profile')->assertOk();
    $this->assertAuthenticatedAs($user->refresh());
});
