<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Users\Actions\CreateUserAction;
use Modules\Users\Actions\DeleteUsersAction;
use Modules\Users\Data\CreateUserData;
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

it('rejects uncapped role and permission arrays with 422', function (): void {
    // Both arrays are resolved in one `whereIn` each; the max rule still stops
    // an oversized payload before the batched rule runs (OWASP API4).
    $roles = array_map(static fn (int $i): string => 'ROLE_'.$i, range(1, 51));
    $permissions = array_map(static fn (int $i): string => 'PERM_'.$i, range(1, 601));

    $this->actingAs($this->admin)
        ->postJson('/users', [
            'first_name' => 'Over',
            'email' => 'over@gmail.com',
            'roles' => $roles,
            'permissions' => $permissions,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles', 'permissions']);
});

it('rejects an unknown role or permission on create with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/users', [
            'first_name' => 'Ghost',
            'email' => 'ghost@gmail.com',
            'roles' => ['NOT_A_ROLE'],
            'permissions' => ['NOT_A_PERMISSION'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles', 'permissions']);
});

it('maps a concurrent unique-constraint race to a validation error', function (): void {
    User::factory()->create(['email' => 'race@gmail.com']);

    $data = CreateUserData::from([
        'first_name' => 'Race',
        'email' => 'race@gmail.com',
    ]);

    expect(fn () => app(CreateUserAction::class)->execute($data, $this->admin))
        ->toThrow(ValidationException::class);
});

it('filters the list by the created_at window', function (): void {
    $january = User::factory()->create();
    $january->forceFill(['created_at' => '2026-01-15 12:00:00'])->saveQuietly();

    $february = User::factory()->create();
    $february->forceFill(['created_at' => '2026-02-15 12:00:00'])->saveQuietly();

    $this->actingAs($this->admin)
        ->getJson('/users?'.http_build_query([
            'filter' => [
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.uuid', $january->uuid)
        ->assertJsonMissingPath('rows.1');
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
        'email' => 'juan@gmail.com',
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
        ->assertJsonPath('email', 'juan@gmail.com')
        ->assertJsonPath('roles.0', 'MANAGER')
        ->assertJsonPath('direct_permissions.0', 'VIEW_USER');

    Queue::assertPushed(SendUserInvitationJob::class);

    $user = User::query()->where('email', 'juan@gmail.com')->firstOrFail();

    expect($user->registered_by_id)->toBe($this->admin->id)
        ->and($user->hasRole('MANAGER'))->toBeTrue()
        ->and($user->hasDirectPermission('VIEW_USER'))->toBeTrue()
        ->and($user->status->value)->toBe('invited')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->invitation_sent_at)->not->toBeNull()
        ->and($user->must_change_password)->toBeFalse();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'user.created',
        'subject_id' => $user->getKey(),
    ]);
});

it('forbids a delegated manager from creating users even with every Users permission', function (): void {
    $manager = UserTestFixtures::delegatedAdmin();

    $this->actingAs($manager)
        ->postJson('/users', [
            'first_name' => 'Nope',
            'email' => 'nope@gmail.com',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'nope@gmail.com']);
});

it('forbids a delegated manager from updating any user', function (): void {
    $manager = UserTestFixtures::delegatedAdmin();
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->putJson('/users/'.$target->uuid, [
            'first_name' => 'Nope',
            'email' => $target->email,
        ])
        ->assertForbidden();
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

it('creates an active account with a temporary password that must be changed', function (): void {
    Queue::fake();

    $this->actingAs($this->admin)
        ->postJson('/users', [
            'first_name' => 'Manual',
            'email' => 'manual@gmail.com',
            'send_invitation' => false,
            'temporary_password' => 'temppass1',
            'temporary_password_confirmation' => 'temppass1',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('must_change_password', true)
        ->assertJsonPath('email_verified', true);

    Queue::assertNotPushed(SendUserInvitationJob::class);

    $user = User::query()->where('email', 'manual@gmail.com')->firstOrFail();

    expect($user->status->value)->toBe('active')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('temppass1', (string) $user->password))->toBeTrue();
});

it('requires a temporary password when the invitation path is disabled', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/users', [
            'first_name' => 'Manual',
            'email' => 'manual@gmail.com',
            'send_invitation' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['temporary_password']);
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

it('filters by account status and sorts by name', function (): void {
    UserTestFixtures::role('SORTABLE', []);

    $charlie = UserTestFixtures::userWithRole('SORTABLE');
    $charlie->forceFill(['first_name' => 'Charlie', 'status' => 'active'])->save();

    $alpha = UserTestFixtures::userWithRole('SORTABLE');
    $alpha->forceFill(['first_name' => 'Alpha', 'status' => 'active'])->save();

    $invited = User::factory()->invited()->create(['first_name' => 'Invitee']);
    $invited->assignRole('SORTABLE');

    $this->actingAs($this->admin)
        ->getJson('/users?'.http_build_query([
            'filter' => ['account_status' => 'invited'],
            'search' => 'Invitee',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.uuid', $invited->uuid);

    $response = $this->actingAs($this->admin)
        ->getJson('/users?'.http_build_query([
            'filter' => ['role' => ['SORTABLE'], 'account_status' => 'active'],
            'sort' => 'name',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 2);

    expect($response->json('rows.0.first_name'))->toBe('Alpha')
        ->and($response->json('rows.1.first_name'))->toBe('Charlie');
});

it('rejects an empty bulk payload with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/users/bulk-delete', ['uuids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['uuids']);
});

it('refuses an action-level bulk delete when one row is above the ceiling and deletes nothing', function (): void {
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

    // The HTTP surface is super-admin-only (`can:manage-staff`), so a 403 from
    // the route would prove the gate, not the ceiling. The action is called
    // directly: the manageable row and the above-ceiling row share one
    // transaction, and the denied row must roll the first one back.
    expect(fn () => app(DeleteUsersAction::class)->execute(
        $manager,
        [$manageable->uuid, $aboveCeiling->uuid],
    ))->toThrow(AuthorizationException::class);

    $this->assertNotSoftDeleted('users', ['uuid' => $manageable->uuid]);
    $this->assertNotSoftDeleted('users', ['uuid' => $aboveCeiling->uuid]);
});
