<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Actions\CreateRoleAction;
use Modules\AccessControl\Data\RoleInputData;
use Modules\AccessControl\Tests\Support\AccessControlFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    AccessControlFixtures::seedPermissions();
    $this->admin = AccessControlFixtures::superAdmin();
});

it('redirects a guest to login', function (): void {
    $this->get('/roles')->assertRedirect('/login');
});

it('forbids a delegated user who holds every fine-grained permission', function (): void {
    $this->actingAs(AccessControlFixtures::delegatedAdmin())
        ->get('/roles')
        ->assertForbidden();
});

it('renders the role index with filters, counts and abilities', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER', [PermissionName::ViewAnyRole->value]);

    $this->actingAs($this->admin)
        ->get('/roles?search=CLAIMS&filter[type]=custom&sort=-permissions_count')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AccessControl/Roles/Index')
            ->has('rows', 1)
            ->where('rows.0.uuid', $role->uuid)
            ->where('rows.0.name', 'CLAIMS_REVIEWER')
            ->where('rows.0.is_system', false)
            ->where('rows.0.permissions_count', 1)
            ->where('rows.0.users_count', 0)
            ->where('filters.type', 'custom')
            ->where('filters.sort_field', 'permissions_count')
            ->where('filters.sort_order', -1)
            ->has('filterOptions.permissions')
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canExport', true)
        );
});

it('keeps the JSON list contract', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/roles')
        ->assertOk()
        ->assertJsonStructure(['rows', 'total', 'page', 'per_page']);
});

it('creates a role with a synced permission set and audits it', function (): void {
    Permission::findOrCreate('VIEW_ANY_USER', 'web');

    $response = $this->actingAs($this->admin)
        ->postJson('/roles', [
            'name' => 'claims_reviewer',
            'permissions' => ['VIEW_ANY_USER', PermissionName::ViewAnyRole->value],
        ])
        ->assertCreated()
        ->assertJsonPath('role.name', 'CLAIMS_REVIEWER')
        ->assertJsonPath('role.permissions_count', 2);

    $role = Role::findByName('CLAIMS_REVIEWER', 'web');
    expect($role->hasPermissionTo('VIEW_ANY_USER'))->toBeTrue()
        ->and($role->hasPermissionTo(PermissionName::ViewAnyRole->value))->toBeTrue();

    expect($response->json('role.uuid'))->toBe($role->uuid);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'role.created',
        'log_name' => 'access-control',
    ]);
});

it('rejects a duplicate role name', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $this->actingAs($this->admin)
        ->postJson('/roles', ['name' => 'CLAIMS_REVIEWER', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects an invalid role name', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/roles', ['name' => 'bad name!', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('refuses to create a role with a reserved system name', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/roles', ['name' => 'ADMIN', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects a role permission that does not exist', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/roles', [
            'name' => 'CLAIMS_REVIEWER',
            'permissions' => ['DOES_NOT_EXIST'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions']);
});

it('refuses to rename a custom role to a reserved system name', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $this->actingAs($this->admin)
        ->putJson('/roles/'.$role->uuid, ['name' => 'ADMIN', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates a custom role name and replaces its permissions', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER', [PermissionName::ViewAnyRole->value]);

    $this->actingAs($this->admin)
        ->putJson('/roles/'.$role->uuid, [
            'name' => 'CLAIMS_LEAD',
            'permissions' => [PermissionName::UpdateRole->value],
        ])
        ->assertOk()
        ->assertJsonPath('role.name', 'CLAIMS_LEAD')
        ->assertJsonPath('role.permissions_count', 1);

    $role->refresh();
    expect($role->name)->toBe('CLAIMS_LEAD')
        ->and($role->hasPermissionTo(PermissionName::UpdateRole->value))->toBeTrue()
        ->and($role->hasPermissionTo(PermissionName::ViewAnyRole->value))->toBeFalse();
});

it('refuses to rename a system role but still syncs its permissions', function (): void {
    $role = Role::findByName('SUPER_ADMIN', 'web');

    $this->actingAs($this->admin)
        ->putJson('/roles/'.$role->uuid, [
            'name' => 'ROOT',
            'permissions' => [PermissionName::ViewAnyRole->value],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($this->admin)
        ->putJson('/roles/'.$role->uuid, [
            'name' => 'SUPER_ADMIN',
            'permissions' => [PermissionName::ViewAnyRole->value],
        ])
        ->assertOk();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($role->fresh()->hasPermissionTo(PermissionName::ViewAnyRole->value))->toBeTrue();
});

it('records the permission names a role update added and removed', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER', [
        PermissionName::ViewAnyRole->value,
        PermissionName::UpdateRole->value,
    ]);

    $this->actingAs($this->admin)
        ->putJson('/roles/'.$role->uuid, [
            'name' => 'CLAIMS_REVIEWER',
            'permissions' => [
                PermissionName::ViewAnyRole->value,
                PermissionName::CreateRole->value,
            ],
        ])
        ->assertOk();

    $properties = Activity::query()
        ->where('event', 'role.updated')
        ->latest('id')
        ->firstOrFail()
        ->properties;

    expect($properties['permissions_added'])->toBe([PermissionName::CreateRole->value])
        ->and($properties['permissions_removed'])->toBe([PermissionName::UpdateRole->value]);
});

it('deletes a custom unassigned role and audits it', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $this->actingAs($this->admin)
        ->deleteJson('/roles/'.$role->uuid)
        ->assertNoContent();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
    $this->assertDatabaseHas('activity_log', [
        'event' => 'role.deleted',
        'log_name' => 'access-control',
    ]);
});

it('refuses to delete a system role', function (): void {
    $role = Role::findByName('SUPER_ADMIN', 'web');

    $this->actingAs($this->admin)
        ->deleteJson('/roles/'.$role->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('refuses to delete a role assigned to a user', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER');
    User::factory()->create()->assignRole($role);

    $this->actingAs($this->admin)
        ->deleteJson('/roles/'.$role->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('returns 404 for an unknown role', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/roles/999999', ['name' => 'MISSING', 'permissions' => []])
        ->assertNotFound();
});

it('flushes the permission cache and audits it', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/access-control/cache-reset')
        ->assertNoContent();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'access.cache_reset',
        'log_name' => 'access-control',
    ]);
});

it('searches role names case-insensitively', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $this->actingAs($this->admin)
        ->getJson('/roles?search=claims')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.name', 'CLAIMS_REVIEWER');
});

it('treats LIKE wildcards in the search as literals', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER');

    $this->actingAs($this->admin)
        ->getJson('/roles?search='.urlencode('%'))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('refuses to delete a role assigned only to a soft-deleted user', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER');
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->delete();

    $this->actingAs($this->admin)
        ->deleteJson('/roles/'.$role->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('re-asserts the reserved system-name invariant at the action boundary', function (): void {
    expect(fn () => app(CreateRoleAction::class)->execute(
        new RoleInputData('ADMIN', []),
        $this->admin,
    ))->toThrow(ValidationException::class);
});

it('rejects an overlong permission name on a role', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/roles', [
            'name' => 'CLAIMS_REVIEWER',
            'permissions' => [str_repeat('A', 65)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});
