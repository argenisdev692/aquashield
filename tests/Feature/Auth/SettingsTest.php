<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest from the settings entry point', function (): void {
    $this->get('/settings')->assertRedirect('/login');
});

it('sends the settings entry point straight to the tabbed account screen', function (): void {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $user->refresh();

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect('/settings/profile');
});
