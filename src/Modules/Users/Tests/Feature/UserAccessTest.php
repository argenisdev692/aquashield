<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Users\Tests\Support\UserTestFixtures;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('returns the role and permission catalog', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users/access-catalog')
        ->assertOk()
        ->assertJsonStructure(['roles', 'permissions'])
        ->assertJsonFragment(['name' => 'SUPER_ADMIN']);
});

it('forbids the catalog without VIEW_ANY_USER', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/users/access-catalog')
        ->assertForbidden();
});

it('assigns a role within the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER']);
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid.'/roles', ['roles' => ['MANAGER']])
        ->assertOk()
        ->assertJsonPath('roles.0', 'MANAGER');

    expect($target->fresh()->hasRole('MANAGER'))->toBeTrue();
});

it('forbids a delegated manager from assigning roles through the HTTP surface', function (): void {
    $manager = UserTestFixtures::delegatedAdmin();
    UserTestFixtures::role('ADMIN', ['EXPORT_USER']);
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->putJson('/users/'.$target->uuid.'/roles', ['roles' => ['ADMIN']])
        ->assertForbidden();

    expect($target->fresh()->hasRole('ADMIN'))->toBeFalse();
});

it('forbids changing your own roles', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/users/'.$this->admin->uuid.'/roles', ['roles' => []])
        ->assertForbidden();
});

it('rejects a role list above the bounded maximum with 422', function (): void {
    $target = User::factory()->create();
    $roles = array_map(static fn (int $i): string => 'ROLE_'.$i, range(1, 51));

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid.'/roles', ['roles' => $roles])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles']);
});

it('rejects a permission list above the bounded maximum with 422', function (): void {
    $target = User::factory()->create();
    $permissions = array_map(static fn (int $i): string => 'PERM_'.$i, range(1, 601));

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid.'/permissions', ['permissions' => $permissions])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions']);
});

it('rejects an unknown role or permission name in one batched check', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid.'/roles', ['roles' => ['NOT_A_ROLE']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles']);

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid.'/permissions', ['permissions' => ['NOT_A_PERMISSION']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions']);
});

it('assigns direct permissions without dropping the role-derived ones', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER']);
    $target = UserTestFixtures::userWithRole('MANAGER');

    $this->actingAs($this->admin)
        ->putJson('/users/'.$target->uuid.'/permissions', ['permissions' => ['CREATE_USER']])
        ->assertOk()
        ->assertJsonPath('direct_permissions.0', 'CREATE_USER');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $target->refresh();

    expect($target->can('VIEW_USER'))->toBeTrue()
        ->and($target->can('CREATE_USER'))->toBeTrue();
});

it('forbids a delegated manager from assigning direct permissions through the HTTP surface', function (): void {
    $manager = UserTestFixtures::delegatedAdmin();
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->putJson('/users/'.$target->uuid.'/permissions', ['permissions' => ['DELETE_USER']])
        ->assertForbidden();

    expect($target->fresh()->hasDirectPermission('DELETE_USER'))->toBeFalse();
});

it('forbids changing your own direct permissions', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/users/'.$this->admin->uuid.'/permissions', ['permissions' => []])
        ->assertForbidden();
});

it('forbids role assignment without ASSIGN_ROLES_USER', function (): void {
    $operator = User::factory()->create();
    $operator->givePermissionTo('VIEW_ANY_USER');
    $target = User::factory()->create();

    $this->actingAs($operator)
        ->putJson('/users/'.$target->uuid.'/roles', ['roles' => []])
        ->assertForbidden();
});

it('adds direct permissions to a role without granting the staff management capability', function (): void {
    Queue::fake();

    // A role can grant operational access; direct permissions are the explicit
    // override on top. Neither is SUPER_ADMIN, so the users surface stays 403.
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER']);
    $juan = UserTestFixtures::userWithRole('MANAGER');

    expect($juan->can('VIEW_USER'))->toBeFalse()
        ->and($juan->can('CREATE_USER'))->toBeFalse();

    $this->actingAs($this->admin)
        ->putJson('/users/'.$juan->uuid.'/permissions', [
            'permissions' => ['VIEW_USER', 'CREATE_USER'],
        ])
        ->assertOk();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $juan->refresh();

    expect($juan->can('VIEW_USER'))->toBeTrue()
        ->and($juan->can('CREATE_USER'))->toBeTrue()
        ->and($juan->can('manage-staff'))->toBeFalse();

    $this->actingAs($juan)
        ->getJson('/users')
        ->assertForbidden();

    $this->actingAs($juan)
        ->postJson('/users', ['first_name' => 'Ana', 'email' => 'ana@gmail.com'])
        ->assertForbidden();
});

it('keeps a second Manager without the direct grant unable to read or create', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER']);
    $maria = UserTestFixtures::userWithRole('MANAGER');
    $target = User::factory()->create();

    $this->actingAs($maria)
        ->getJson('/users/'.$target->uuid)
        ->assertForbidden();

    $this->actingAs($maria)
        ->postJson('/users', ['first_name' => 'Nope', 'email' => 'nope@gmail.com'])
        ->assertForbidden();
});
