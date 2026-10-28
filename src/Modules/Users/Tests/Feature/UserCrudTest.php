<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

/* ------------------------------------------------------ 401 unauthenticated */

it('redirects a guest from the index to login', function (): void {
    $this->get('/users')->assertRedirect('/login');
});

it('answers 401 to guest JSON requests', function (): void {
    $this->getJson('/users')->assertUnauthorized();
    $this->postJson('/users', [])->assertUnauthorized();
    $this->deleteJson('/users/'.Str::uuid7()->toString())->assertUnauthorized();
});

/* --------------------------------------------------------- 403 permission */

it('forbids a user without VIEW_ANY_USER', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/users')->assertForbidden();
});

it('forbids a user without VIEW_USER from showing one row', function (): void {
    $target = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('VIEW_ANY_USER');

    $this->actingAs($viewer)->getJson('/users/'.$target->uuid)->assertForbidden();
});

/* -------------------------------------------------------------- 422 input */

it('rejects invalid create input with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/users', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'email']);
});

it('rejects invalid update input with 422', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid, ['first_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'email']);
});

it('rejects a non-allowlisted sort with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users?sort=password')
        ->assertUnprocessable();
});

it('rejects an unknown filter with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users?'.http_build_query(['filter' => ['unknown' => 'x']]))
        ->assertUnprocessable();
});

/* ------------------------------------------------------------- read side */

it('lists users and never exposes persistence secrets', function (): void {
    $target = User::factory()->create([
        'first_name' => 'Juana',
        'last_name' => 'Perez',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/users?search=Juana')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.uuid', $target->uuid)
        ->assertJsonPath('rows.0.first_name', 'Juana')
        ->assertJsonMissingPath('rows.0.id')
        ->assertJsonMissingPath('rows.0.password')
        ->assertJsonMissingPath('rows.0.remember_token');
});

it('filters the list by role', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER']);
    $target = UserTestFixtures::userWithRole('MANAGER');

    $this->actingAs($this->admin)
        ->getJson('/users?'.http_build_query(['filter' => ['role' => 'MANAGER']]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.uuid', $target->uuid);
});

it('shows trashed users when the status filter asks for them', function (): void {
    $target = User::factory()->create();
    $target->delete();

    $response = $this->actingAs($this->admin)
        ->getJson('/users?'.http_build_query(['filter' => ['status' => 'trashed']]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.uuid', $target->uuid);

    expect($response->json('rows.0.deleted_at'))->not->toBeNull();
});

it('shows one user', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->getJson('/users/'.$target->uuid)
        ->assertOk()
        ->assertJsonPath('uuid', $target->uuid);
});

it('404s a malformed uuid before the controller', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users/not-a-uuid')
        ->assertNotFound();
});

/* ------------------------------------------------------------------ write */

it('creates a user with a role and a direct permission and queues the invitation', function (): void {
    Queue::fake();

    UserTestFixtures::role('MANAGER', ['VIEW_USER']);

    $payload = [
        'first_name' => 'Juan',
        'last_name' => 'Perez',
        'username' => 'juan.perez',
        'email' => 'juan@example.com',
        'phone' => '+1 555 0100',
        'date_of_birth' => '1990-01-01',
        'city' => 'Houston',
        'state' => 'TX',
        'country' => 'US',
        'roles' => ['MANAGER'],
        'permissions' => ['VIEW_USER'],
    ];

    $this->actingAs($this->admin)
        ->postJson('/users', $payload)
        ->assertCreated()
        ->assertJsonPath('email', 'juan@example.com')
        ->assertJsonPath('roles.0', 'MANAGER')
        ->assertJsonPath('direct_permissions.0', 'VIEW_USER');

    Queue::assertPushed(SendUserInvitationJob::class);

    $user = User::query()->where('email', 'juan@example.com')->firstOrFail();

    expect($user->registered_by_id)->toBe($this->admin->id)
        ->and($user->hasRole('MANAGER'))->toBeTrue()
        ->and($user->hasDirectPermission('VIEW_USER'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'user.created',
        'subject_id' => $user->getKey(),
    ]);
});

it('refuses to create a user with a role above the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER', 'VIEW_USER', 'CREATE_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    UserTestFixtures::role('ADMIN', ['DELETE_USER']);

    $this->actingAs($manager)
        ->postJson('/users', [
            'first_name' => 'Nope',
            'email' => 'nope@example.com',
            'roles' => ['ADMIN'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles']);

    $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
});

it('refuses to create a user with a direct permission above the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER', 'VIEW_USER', 'CREATE_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    $this->actingAs($manager)
        ->postJson('/users', [
            'first_name' => 'Nope',
            'email' => 'nope@example.com',
            'permissions' => ['DELETE_USER'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions']);

    $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
});

it('updates a user profile', function (): void {
    $target = User::factory()->create(['city' => 'Houston']);

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid, [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => $target->email,
            'city' => 'Dallas',
        ])
        ->assertOk()
        ->assertJsonPath('city', 'Dallas');

    $this->assertDatabaseHas('users', [
        'uuid' => $target->uuid,
        'city' => 'Dallas',
    ]);
});

it('forbids updating a user above the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER', 'VIEW_USER', 'UPDATE_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    UserTestFixtures::role('ADMIN', ['EXPORT_USER']);
    $target = UserTestFixtures::userWithRole('ADMIN');

    $this->actingAs($manager)
        ->putJson('/users/'.$target->uuid, [
            'first_name' => 'Nope',
            'email' => $target->email,
        ])
        ->assertForbidden();
});

it('soft deletes a user', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/users/'.$target->uuid)
        ->assertNoContent();

    $this->assertSoftDeleted('users', ['uuid' => $target->uuid]);
});

it('forbids deleting yourself', function (): void {
    $this->actingAs($this->admin)
        ->deleteJson('/users/'.$this->admin->uuid)
        ->assertForbidden();
});

it('restores a soft deleted user', function (): void {
    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($this->admin)
        ->patchJson('/users/'.$target->uuid.'/restore')
        ->assertOk()
        ->assertJsonPath('uuid', $target->uuid);

    $this->assertNotSoftDeleted('users', ['uuid' => $target->uuid]);
});

it('rejects restoring a user that is not deleted with 422', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson('/users/'.$target->uuid.'/restore')
        ->assertUnprocessable();
});

it('bulk deletes and bulk restores the same rows', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-delete', ['uuids' => [$first->uuid, $second->uuid]])
        ->assertNoContent();

    $this->assertSoftDeleted('users', ['uuid' => $first->uuid]);
    $this->assertSoftDeleted('users', ['uuid' => $second->uuid]);

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-restore', ['uuids' => [$first->uuid, $second->uuid]])
        ->assertNoContent();

    $this->assertNotSoftDeleted('users', ['uuid' => $first->uuid]);
    $this->assertNotSoftDeleted('users', ['uuid' => $second->uuid]);
});

it('rejects an empty bulk payload with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/users/bulk-delete', ['uuids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['uuids']);
});

it('refuses a bulk delete when one row is above the ceiling and deletes nothing', function (): void {
    UserTestFixtures::role('MANAGER', [
        'VIEW_ANY_USER',
        'VIEW_USER',
        'DELETE_USER',
        'BULK_DELETE_USER',
    ]);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    UserTestFixtures::role('ADMIN', ['EXPORT_USER']);

    $manageable = User::factory()->create();
    $aboveCeiling = UserTestFixtures::userWithRole('ADMIN');

    $this->actingAs($manager)
        ->postJson('/users/bulk-delete', ['uuids' => [$manageable->uuid, $aboveCeiling->uuid]])
        ->assertForbidden();

    $this->assertNotSoftDeleted('users', ['uuid' => $manageable->uuid]);
    $this->assertNotSoftDeleted('users', ['uuid' => $aboveCeiling->uuid]);
});
