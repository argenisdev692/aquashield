<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the confirmation screen for a signed-in user', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/user/confirm-password')
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ConfirmPassword'));
});

it('refuses a wrong password and records no confirmation', function (): void {
    $this->actingAs(User::factory()->create())
        ->from('/user/confirm-password')
        ->post('/user/confirm-password', ['password' => 'not-the-password'])
        ->assertSessionHasErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
});

it('confirms a correct password', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/user/confirm-password', ['password' => 'password'])
        ->assertSessionHas('auth.password_confirmed_at');
});

it('sends a signed-in guest to the sign-in screen', function (): void {
    $this->get('/user/confirm-password')->assertRedirect('/login');
});
