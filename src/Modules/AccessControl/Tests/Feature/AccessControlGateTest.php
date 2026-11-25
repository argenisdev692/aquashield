<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AccessControl\Tests\Support\AccessControlFixtures;
use Modules\Users\Enums\UserStatus;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    AccessControlFixtures::seedPermissions();
    $this->admin = AccessControlFixtures::superAdmin();
});

it('answers 401 to an unauthenticated JSON request', function (): void {
    $this->getJson('/roles')->assertUnauthorized();
});

it('refuses a JSON write for a guest with 401', function (): void {
    $this->postJson('/roles', ['name' => 'NOPE', 'permissions' => []])
        ->assertUnauthorized();
});

it('forbids a user with every fine-grained permission but no SUPER_ADMIN role', function (): void {
    $this->actingAs(AccessControlFixtures::delegatedAdmin())
        ->getJson('/roles')
        ->assertForbidden();

    $this->actingAs(AccessControlFixtures::delegatedAdmin())
        ->postJson('/access-control/cache-reset')
        ->assertForbidden();
});

it('forbids an inactive super administrator', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Suspended]);
    $user->assignRole(RoleName::SuperAdmin->value);

    $this->actingAs($user)
        ->getJson('/roles')
        ->assertForbidden();
});

it('forbids a super administrator stripped of the fine-grained permission', function (): void {
    Role::findByName(RoleName::SuperAdmin->value, 'web')
        ->revokePermissionTo(PermissionName::ViewAnyRole->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->getJson('/roles')
        ->assertForbidden();
});

it('forbids the create route without CREATE_ROLE while the gate still passes', function (): void {
    Role::findByName(RoleName::SuperAdmin->value, 'web')
        ->revokePermissionTo(PermissionName::CreateRole->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->postJson('/roles', ['name' => 'NOPE', 'permissions' => []])
        ->assertForbidden();
});

it('forbids role and permission mutations without the fine-grained permission', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER');
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson('/roles/create')->assertForbidden();
    $this->actingAs($outsider)->getJson('/roles/'.$role->uuid.'/edit')->assertForbidden();
    $this->actingAs($outsider)
        ->putJson('/roles/'.$role->uuid, ['name' => 'RENAMED', 'permissions' => []])
        ->assertForbidden();
    $this->actingAs($outsider)->deleteJson('/roles/'.$role->uuid)->assertForbidden();

    $this->actingAs($outsider)->getJson('/permissions')->assertForbidden();
    $this->actingAs($outsider)->postJson('/permissions', ['name' => 'NEW_PERMISSION'])->assertForbidden();
    $this->actingAs($outsider)
        ->putJson('/permissions/'.$permission->uuid, ['name' => 'RENAMED_PERMISSION'])
        ->assertForbidden();
    $this->actingAs($outsider)->deleteJson('/permissions/'.$permission->uuid)->assertForbidden();
});

it('forbids the permission export without its capability pair', function (): void {
    Role::findByName(RoleName::SuperAdmin->value, 'web')
        ->revokePermissionTo(PermissionName::ExportPermission->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->get('/permissions/export/csv')
        ->assertForbidden();
});

it('enforces every fine-grained permission while the gate still passes', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER');
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');
    $super = Role::findByName(RoleName::SuperAdmin->value, 'web');

    /** @var list<array{string, string, PermissionName, array<string, mixed>}> $cases */
    $cases = [
        ['GET', '/roles/create', PermissionName::CreateRole, []],
        ['GET', '/roles/'.$role->uuid.'/edit', PermissionName::UpdateRole, []],
        ['PUT', '/roles/'.$role->uuid, PermissionName::UpdateRole, ['name' => 'RENAMED', 'permissions' => []]],
        ['DELETE', '/roles/'.$role->uuid, PermissionName::DeleteRole, []],
        ['GET', '/permissions', PermissionName::ViewAnyPermission, []],
        ['POST', '/permissions', PermissionName::CreatePermission, ['name' => 'NEW_PERMISSION']],
        ['PUT', '/permissions/'.$permission->uuid, PermissionName::UpdatePermission, ['name' => 'RENAMED_PERMISSION']],
        ['DELETE', '/permissions/'.$permission->uuid, PermissionName::DeletePermission, []],
        ['POST', '/access-control/cache-reset', PermissionName::UpdatePermission, []],
    ];

    foreach ($cases as [$method, $uri, $capability, $payload]) {
        $super->revokePermissionTo($capability->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->admin)
            ->json($method, $uri, $payload)
            ->assertForbidden();

        $super->givePermissionTo($capability->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
});
