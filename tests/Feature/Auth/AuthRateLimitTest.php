<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('throttles repeated password-confirmation attempts', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->post('/user/confirm-password', ['password' => 'wrong-password'])
            ->assertRedirect();
    }

    $this->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'wrong-password'])
        ->assertStatus(429);
});

it('throttles repeated current-password checks on the password update route', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->put('/user/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertRedirect();
    }

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])
        ->assertStatus(429);
});

it('throttles password reset link requests', function (): void {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect();
    }

    $this->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertStatus(429);
});

it('adds a per-IP ceiling to email OTP sending', function (): void {
    Notification::fake();

    foreach (range(1, 10) as $attempt) {
        $this->post('/login/otp', ['email' => "person{$attempt}@example.com"])
            ->assertRedirect('/login/otp');
    }

    $this->post('/login/otp', ['email' => 'person11@example.com'])
        ->assertStatus(429);
});

it('throttles password reset submissions per IP', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'nobody@example.com',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect();
    }

    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'nobody@example.com',
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertStatus(429);
});
