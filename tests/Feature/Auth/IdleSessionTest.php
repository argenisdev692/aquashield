<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The dialog is client-side UX; this middleware is the trust boundary. These
 * tests pin the server's half: idle sessions are invalidated, live sessions
 * slide, and the keep-alive route is the only explicit refresh.
 */
it('shares the idle policy with authenticated pages', function (): void {
    $user = User::factory()->create()->refresh();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('authIdle.warnAfterSeconds', 300)
            ->where('authIdle.graceSeconds', 60)
            ->where('authIdle.keepAliveEndpoint', route('auth.keep-alive'))
            ->where('authIdle.logoutEndpoint', route('logout')));
});

it('does not share the idle policy with guests', function (): void {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('authIdle', null));
});

it('logs out an idle session on the next authenticated request', function (): void {
    $user = User::factory()->create()->refresh();

    $this->actingAs($user)
        ->withSession(['auth.last_activity_at' => now()->subMinutes(7)->getTimestamp()])
        ->get('/dashboard')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('keeps a session that is still inside the grace period', function (): void {
    $user = User::factory()->create()->refresh();

    $this->actingAs($user)
        ->withSession(['auth.last_activity_at' => now()->subMinutes(6)->getTimestamp()])
        ->get('/dashboard')
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('responds to an Inertia request with the location protocol instead of a redirect', function (): void {
    $user = User::factory()->create()->refresh();

    $this->actingAs($user)
        ->withSession(['auth.last_activity_at' => now()->subMinutes(7)->getTimestamp()])
        ->withHeaders(['X-Inertia' => 'true'])
        ->get('/dashboard')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));

    $this->assertGuest();
});

it('refreshes the server timestamp through the keep-alive route', function (): void {
    $user = User::factory()->create()->refresh();
    $stale = now()->subMinutes(6)->getTimestamp();

    $response = $this->actingAs($user)
        ->withSession(['auth.last_activity_at' => $stale])
        ->post('/auth/keep-alive');

    $response->assertNoContent();
    $response->assertSessionHas('auth.last_activity_at');

    expect((int) session('auth.last_activity_at'))->toBeGreaterThan($stale);

    $this->assertAuthenticatedAs($user);
});
