<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the verification prompt with the signed-in address', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/email/verify')
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', $user->email),
        );
});

it('resends the verification link and flashes the status', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from('/email/verify')
        ->post('/email/verification-notification')
        ->assertRedirect('/email/verify')
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('requires authentication', function (): void {
    $this->get('/email/verify')->assertRedirect('/login');
});
