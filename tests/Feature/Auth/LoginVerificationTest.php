<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;

uses(RefreshDatabase::class);

it('refuses an unverified password login and keeps the visitor a guest', function (): void {
    $user = User::factory()->unverified()->create([
        'password' => Hash::make('password'),
    ]);

    $this->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    /** @var ViewErrorBag $errors */
    $errors = session('errors');

    expect($errors->getBag('default')->first('email'))
        ->toContain('not verified');

    $this->assertGuest();
});

it('allows a verified password login', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('redirects an unverified session away from the dashboard', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/email/verify');
});
