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

it('refuses a profile photo upload from a guest with 401', function (): void {
    $this->postJson('/settings/profile/photo', [])->assertUnauthorized();
});

it('stores the profile photo through R2 and records the object key', function (): void {
    $user = User::factory()->create();
    $user->refresh();

    $response = $this->actingAs($user)->post(
        '/settings/profile/photo',
        ['photo' => UploadedFile::fake()->image('photo.jpg', 600, 600)->size(300)],
        ['Accept' => 'application/json'],
    );

    $response->assertOk()->assertJsonStructure(['profile_photo_url']);

    $path = $user->refresh()->profile_photo_path;

    expect($path)->toBeString()
        ->and($path)->toStartWith('profile-photos/'.$user->uuid.'/')
        ->and($path)->toEndWith('.webp');

    Storage::disk('r2')->assertExists($path);
    // Storage::fake uses its own /storage URL prefix; the production adapter
    // resolves the configured R2 public domain instead. Assert the object key
    // is the tail of the returned URL either way.
    expect($response->json('profile_photo_url'))->toBeString()->toContain($path);
});

it('deletes the previous profile photo after a successful replacement', function (): void {
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/old.webp']);
    Storage::disk('r2')->put('profile-photos/old.webp', 'old');

    $this->actingAs($user)->post(
        '/settings/profile/photo',
        ['photo' => UploadedFile::fake()->image('new.png', 400, 400)->size(200)],
        ['Accept' => 'application/json'],
    )->assertOk();

    Storage::disk('r2')->assertMissing('profile-photos/old.webp');
    Storage::disk('r2')->assertExists((string) $user->refresh()->profile_photo_path);
});

it('rejects a non-image profile photo with 422', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(
        '/settings/profile/photo',
        ['photo' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain')],
    )->assertSessionHasErrors('photo');

    expect($user->refresh()->profile_photo_path)->toBeNull();
});

it('removes the current profile photo and its storage object', function (): void {
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/current.webp']);
    Storage::disk('r2')->put('profile-photos/current.webp', 'current');

    $this->actingAs($user)->deleteJson('/settings/profile/photo')
        ->assertOk()
        ->assertJsonPath('profile_photo_url', null);

    expect($user->refresh()->profile_photo_path)->toBeNull();
    Storage::disk('r2')->assertMissing('profile-photos/current.webp');
});
