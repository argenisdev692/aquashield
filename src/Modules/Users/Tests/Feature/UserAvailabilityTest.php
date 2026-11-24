<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('reports a free email and a free username as available', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email&value=free@gmail.com')
        ->assertOk()
        ->assertJson(['available' => true, 'message' => 'Email is available.']);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=username&value=free.handle')
        ->assertOk()
        ->assertJson(['available' => true, 'message' => 'Username is available.']);
});

it('reports a taken email and username as unavailable', function (): void {
    $user = User::factory()->create([
        'email' => 'taken@gmail.com',
        'username' => 'taken.handle',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email&value=taken@gmail.com')
        ->assertOk()
        ->assertJson(['available' => false, 'message' => 'Email is already in use.']);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=username&value='.$user->username)
        ->assertOk()
        ->assertJson(['available' => false, 'message' => 'Username is already in use.']);
});

it('normalises case and whitespace before checking', function (): void {
    User::factory()->create(['email' => 'taken@gmail.com']);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email&value=%20TAKEN@GMAIL.COM%20')
        ->assertOk()
        ->assertJsonPath('available', false);
});

it('ignores the edited user so an unchanged value stays available', function (): void {
    $user = User::factory()->create([
        'email' => 'self@gmail.com',
        'username' => 'self.handle',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email&value=self@gmail.com&ignore_user_id='.$user->uuid)
        ->assertOk()
        ->assertJsonPath('available', true);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=username&value=self.handle&ignore_user_id='.$user->uuid)
        ->assertOk()
        ->assertJsonPath('available', true);
});

it('counts soft-deleted rows as taken because their unique index still exists', function (): void {
    $user = User::factory()->create(['email' => 'deleted@gmail.com']);
    $user->delete();

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email&value=deleted@gmail.com')
        ->assertOk()
        ->assertJsonPath('available', false);
});

it('rejects malformed values before reporting them free', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email&value=not-an-email')
        ->assertOk()
        ->assertJsonPath('available', false);

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=username&value=Bad Name!')
        ->assertOk()
        ->assertJsonPath('available', false);
});

it('validates the probe contract with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=password&value=x')
        ->assertUnprocessable();

    $this->actingAs($this->admin)
        ->getJson('/users/availability?field=email')
        ->assertUnprocessable();
});

it('refuses the probe to a non-super-admin and a guest', function (): void {
    $this->getJson('/users/availability?field=email&value=x@gmail.com')
        ->assertUnauthorized();

    $this->actingAs(UserTestFixtures::delegatedAdmin())
        ->getJson('/users/availability?field=email&value=x@gmail.com')
        ->assertForbidden();
});
