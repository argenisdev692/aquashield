<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('redirects a guest from the settings hub', function (): void {
    $this->get('/settings')->assertRedirect('/login');
});

it('renders the settings hub with the account projection', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    $user->refresh();

    $this->actingAs($user)
        ->get('/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Index')
            ->where('user.name', 'Ada Lovelace')
            ->where('user.email', 'ada@example.com')
            ->where('user.email_verified', true)
            ->where('user.two_factor_enabled', false)
            ->missing('user.password'));
});

it('keeps the dashboard reachable for a verified user', function (): void {
    $user = User::factory()->create();
    $user->refresh();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

it('redirects an unverified user away from the dashboard', function (): void {
    $user = User::factory()->unverified()->create();
    $user->refresh();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/email/verify');
});
