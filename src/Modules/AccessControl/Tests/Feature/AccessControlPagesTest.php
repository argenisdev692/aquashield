<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Tests\Support\AccessControlFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    AccessControlFixtures::seedPermissions();
    $this->admin = AccessControlFixtures::superAdmin();
});

it('renders the create screen with the full catalogue', function (): void {
    $this->actingAs($this->admin)
        ->get('/roles/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AccessControl/Roles/Create')
            ->has('catalog.permissions', 10)
            ->where('catalog.permissions.0.name', 'CREATE_PERMISSION')
        );
});

it('renders the edit screen with the role and its permission names', function (): void {
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER', ['VIEW_ANY_ROLE']);

    $this->actingAs($this->admin)
        ->get('/roles/'.$role->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AccessControl/Roles/Edit')
            ->where('role.uuid', $role->uuid)
            ->where('role.name', 'CLAIMS_REVIEWER')
            ->where('role.is_system', false)
            ->has('role.permissions', 1)
            ->where('role.permissions.0', 'VIEW_ANY_ROLE')
            ->has('catalog.permissions', 10)
        );
});

it('sends the edit screen a canonically ordered permission set', function (): void {
    // Ids ascend VIEW_ANY_ROLE → CREATE_ROLE; alphabetical ascends CREATE_ROLE →
    // VIEW_ANY_ROLE. The form compares arrays index-wise, so the server has to
    // canonicalise the set or `isDirty` never returns to false after a toggle.
    $role = AccessControlFixtures::customRole('CLAIMS_REVIEWER', [
        PermissionName::ViewAnyRole->value,
        PermissionName::CreateRole->value,
    ]);

    $this->actingAs($this->admin)
        ->get('/roles/'.$role->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AccessControl/Roles/Edit')
            ->where('role.permissions', ['CREATE_ROLE', 'VIEW_ANY_ROLE'])
        );
});

it('renders the permission index with its filter options', function (): void {
    $this->actingAs($this->admin)
        ->get('/permissions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AccessControl/Permissions/Index')
            ->has('filterOptions.groups')
            ->has('filterOptions.roles')
            ->where('filterOptions.permissions', [])
            ->where('filterOptions.groups', fn ($groups) => collect($groups)->contains('ROLE'))
            ->where('filterOptions.roles', fn ($roles) => collect($roles)->contains('SUPER_ADMIN'))
            ->where('canCreate', true)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canExport', true)
        );
});
