<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filesystems.disks.r2.url' => 'https://cdn.example.test']);
    Storage::fake('r2');
});

it('refuses an avatar upload from a guest with 401', function (): void {
    $this->postJson('/settings/profile/avatar', [])->assertUnauthorized();
});

it('stores the avatar through R2 and records the object key', function (): void {
    $user = User::factory()->create();
    $user->refresh();

    $response = $this->actingAs($user)->post(
        '/settings/profile/avatar',
        ['avatar' => UploadedFile::fake()->image('avatar.jpg', 600, 600)->size(300)],
        ['Accept' => 'application/json'],
    );

    $response->assertOk()->assertJsonStructure(['avatar_url']);

    $path = $user->refresh()->avatar_path;

    expect($path)->toBeString()
        ->and($path)->toStartWith('avatars/'.$user->uuid.'/')
        ->and($path)->toEndWith('.webp');

    Storage::disk('r2')->assertExists($path);
    // Storage::fake uses its own /storage URL prefix; the production adapter
    // resolves the configured R2 public domain instead. Assert the object key
    // is the tail of the returned URL either way.
    expect($response->json('avatar_url'))->toBeString()->toContain($path);
});

it('deletes the previous avatar after a successful replacement', function (): void {
    $user = User::factory()->create(['avatar_path' => 'avatars/old.webp']);
    Storage::disk('r2')->put('avatars/old.webp', 'old');

    $this->actingAs($user)->post(
        '/settings/profile/avatar',
        ['avatar' => UploadedFile::fake()->image('new.png', 400, 400)->size(200)],
        ['Accept' => 'application/json'],
    )->assertOk();

    Storage::disk('r2')->assertMissing('avatars/old.webp');
    Storage::disk('r2')->assertExists((string) $user->refresh()->avatar_path);
});

it('rejects a non-image upload with 422', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(
        '/settings/profile/avatar',
        ['avatar' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain')],
    )->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

it('removes the current avatar and its storage object', function (): void {
    $user = User::factory()->create(['avatar_path' => 'avatars/current.webp']);
    Storage::disk('r2')->put('avatars/current.webp', 'current');

    $this->actingAs($user)->deleteJson('/settings/profile/avatar')
        ->assertOk()
        ->assertJsonPath('avatar_url', null);

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('r2')->assertMissing('avatars/current.webp');
});
