<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Actions\CreatePermissionAction;
use Modules\AccessControl\Data\PermissionInputData;
use Modules\AccessControl\Tests\Support\AccessControlFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    AccessControlFixtures::seedPermissions();
    $this->admin = AccessControlFixtures::superAdmin();
});

it('renders the permission index with group/type filters', function (): void {
    $this->actingAs($this->admin)
        ->get('/permissions?search=CREATE&filter[type]=system&filter[group]=ROLE&sort=name')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AccessControl/Permissions/Index')
            ->has('rows', 1)
            ->where('rows.0.name', 'CREATE_ROLE')
            ->where('rows.0.group', 'ROLE')
            ->where('rows.0.is_system', true)
            ->where('filters.type', 'system')
            ->where('filters.group', 'ROLE')
            ->has('filterOptions.groups')
            ->has('filterOptions.roles')
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canExport', true)
        );
});

it('keeps the JSON list contract', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/permissions')
        ->assertOk()
        ->assertJsonStructure(['rows', 'total', 'page', 'per_page']);
});

it('filters permissions by role', function (): void {
    AccessControlFixtures::customRole('CLAIMS_REVIEWER', [PermissionName::CreateRole->value]);

    $this->actingAs($this->admin)
        ->getJson('/permissions?filter[role]=CLAIMS_REVIEWER')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.name', 'CREATE_ROLE');
});

it('creates a custom permission and flushes the registry', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/permissions', ['name' => 'approve_claim'])
        ->assertCreated()
        ->assertJsonPath('permission.name', 'APPROVE_CLAIM')
        ->assertJsonPath('permission.group', 'APPROVE_CLAIM')
        ->assertJsonPath('permission.is_system', false);

    $permission = Permission::findByName('APPROVE_CLAIM', 'web');
    expect((bool) $permission->getAttribute('is_system'))->toBeFalse();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all())
        ->toContain('APPROVE_CLAIM');

    $this->assertDatabaseHas('activity_log', [
        'event' => 'permission.created',
        'log_name' => 'access-control',
    ]);
});

it('returns to a safe filtered index after a permission write', function (): void {
    $this->actingAs($this->admin)
        ->post('/permissions', [
            'name' => 'APPROVE_CLAIM',
            'return_to' => '/permissions?search=APPROVE&filter[type]=custom',
        ])
        ->assertRedirect('/permissions?search=APPROVE&filter[type]=custom');
});

it('rejects a duplicate permission name', function (): void {
    AccessControlFixtures::customPermission('APPROVE_CLAIM');

    $this->actingAs($this->admin)
        ->postJson('/permissions', ['name' => 'APPROVE_CLAIM'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('refuses to create a permission reserved by the application', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/permissions', ['name' => 'VIEW_ANY_USER'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('refuses to rename a system permission', function (): void {
    $permission = Permission::findByName('CREATE_ROLE', 'web');

    $this->actingAs($this->admin)
        ->putJson('/permissions/'.$permission->uuid, ['name' => 'MAKE_ROLE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('refuses to delete a system permission', function (): void {
    $permission = Permission::findByName('CREATE_ROLE', 'web');

    $this->actingAs($this->admin)
        ->deleteJson('/permissions/'.$permission->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permission']);
});

it('renames a custom permission without dropping its role grant', function (): void {
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER', ['APPROVE_CLAIM']);

    $this->actingAs($this->admin)
        ->putJson('/permissions/'.$permission->uuid, ['name' => 'APPROVE_CLAIMS'])
        ->assertOk()
        ->assertJsonPath('permission.name', 'APPROVE_CLAIMS');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($role->fresh()->hasPermissionTo('APPROVE_CLAIMS'))->toBeTrue();
});

it('refuses to rename a custom permission to a reserved name', function (): void {
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');

    $this->actingAs($this->admin)
        ->putJson('/permissions/'.$permission->uuid, ['name' => 'VIEW_ANY_USER'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('refuses to delete a custom permission assigned to a role', function (): void {
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');
    AccessControlFixtures::customRole('CLAIMS_REVIEWER', ['APPROVE_CLAIM']);

    $this->actingAs($this->admin)
        ->deleteJson('/permissions/'.$permission->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permission']);
});

it('deletes an unassigned custom permission and audits it', function (): void {
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');

    $this->actingAs($this->admin)
        ->deleteJson('/permissions/'.$permission->uuid)
        ->assertNoContent();

    expect(Permission::query()->whereKey($permission->id)->exists())->toBeFalse();
    $this->assertDatabaseHas('activity_log', [
        'event' => 'permission.deleted',
        'log_name' => 'access-control',
    ]);
});

it('returns 404 for an unknown permission', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/permissions/999999', ['name' => 'MISSING'])
        ->assertNotFound();
});

it('searches permission names case-insensitively', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/permissions?search=create')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('treats LIKE wildcards in the search as literals', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/permissions?search='.urlencode('%'))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('refuses to delete a permission assigned only to a soft-deleted user', function (): void {
    $permission = AccessControlFixtures::customPermission('APPROVE_CLAIM');
    $user = User::factory()->create();
    $user->givePermissionTo($permission);
    $user->delete();

    $this->actingAs($this->admin)
        ->deleteJson('/permissions/'.$permission->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permission']);

    $this->actingAs($this->admin)
        ->getJson('/permissions?search=APPROVE_CLAIM')
        ->assertOk()
        ->assertJsonPath('rows.0.users_count', 1);

    expect(Permission::query()->whereKey($permission->id)->exists())->toBeTrue();
});

it('re-asserts the reserved permission-name invariant at the action boundary', function (): void {
    expect(fn () => app(CreatePermissionAction::class)->execute(
        new PermissionInputData(PermissionName::ViewAnyUser->value),
        $this->admin,
    ))->toThrow(ValidationException::class);
});
