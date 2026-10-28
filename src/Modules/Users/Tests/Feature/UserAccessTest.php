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

it('refuses a role that carries a permission above the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', [
        'VIEW_ANY_USER',
        'VIEW_USER',
        'ASSIGN_ROLES_USER',
    ]);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    UserTestFixtures::role('ADMIN', ['EXPORT_USER']);
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->putJson('/users/'.$target->uuid.'/roles', ['roles' => ['ADMIN']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles']);

    expect($target->fresh()->hasRole('ADMIN'))->toBeFalse();
});

it('forbids changing your own roles', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/users/'.$this->admin->uuid.'/roles', ['roles' => []])
        ->assertForbidden();
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

it('refuses a direct permission above the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', [
        'VIEW_ANY_USER',
        'VIEW_USER',
        'ASSIGN_PERMISSIONS_USER',
    ]);
    $manager = UserTestFixtures::userWithRole('MANAGER');
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->putJson('/users/'.$target->uuid.'/permissions', ['permissions' => ['DELETE_USER']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions']);

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

it('lets a Manager gain READ_USER and CREATE_USER as direct permissions', function (): void {
    Queue::fake();

    // The scenario from the brief: a MANAGER role that grants list access but
    // not VIEW_USER/CREATE_USER. The role stays untouched; the two direct
    // permissions are the explicit grant on top.
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER']);
    $juan = UserTestFixtures::userWithRole('MANAGER');
    $target = User::factory()->create();

    expect($juan->can('VIEW_USER'))->toBeFalse()
        ->and($juan->can('CREATE_USER'))->toBeFalse();

    $this->actingAs($juan)
        ->getJson('/users/'.$target->uuid)
        ->assertForbidden();

    $this->actingAs($juan)
        ->postJson('/users', ['first_name' => 'Nope', 'email' => 'nope@example.com'])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->putJson('/users/'.$juan->uuid.'/permissions', [
            'permissions' => ['VIEW_USER', 'CREATE_USER'],
        ])
        ->assertOk();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $juan->refresh();

    expect($juan->can('VIEW_USER'))->toBeTrue()
        ->and($juan->can('CREATE_USER'))->toBeTrue();

    $this->actingAs($juan)
        ->getJson('/users/'.$target->uuid)
        ->assertOk();

    $this->actingAs($juan)
        ->postJson('/users', ['first_name' => 'Ana', 'email' => 'ana@example.com'])
        ->assertCreated();
});

it('keeps a second Manager without the direct grant unable to read or create', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER']);
    $maria = UserTestFixtures::userWithRole('MANAGER');
    $target = User::factory()->create();

    $this->actingAs($maria)
        ->getJson('/users/'.$target->uuid)
        ->assertForbidden();

    $this->actingAs($maria)
        ->postJson('/users', ['first_name' => 'Nope', 'email' => 'nope@example.com'])
        ->assertForbidden();
});
