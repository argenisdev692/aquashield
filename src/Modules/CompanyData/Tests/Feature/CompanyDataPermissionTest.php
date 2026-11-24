<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds only view and update for the company data singleton', function (): void {
    $this->seed(RolePermissionSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'VIEW_COMPANY_DATA')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'UPDATE_COMPANY_DATA')->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('name', [
            'VIEW_ANY_COMPANY_DATA',
            'CREATE_COMPANY_DATA',
            'DELETE_COMPANY_DATA',
            'RESTORE_COMPANY_DATA',
            'BULK_DELETE_COMPANY_DATA',
            'BULK_RESTORE_COMPANY_DATA',
            'EXPORT_COMPANY_DATA',
        ])->exists())->toBeFalse();

    $superAdmin = Role::findByName(RoleName::SuperAdmin->value, 'web');
    $admin = Role::findByName(RoleName::Admin->value, 'web');

    expect($superAdmin->hasPermissionTo('VIEW_COMPANY_DATA'))->toBeTrue()
        ->and($superAdmin->hasPermissionTo('UPDATE_COMPANY_DATA'))->toBeTrue()
        ->and($admin->hasPermissionTo('VIEW_COMPANY_DATA'))->toBeFalse()
        ->and($admin->hasPermissionTo('UPDATE_COMPANY_DATA'))->toBeFalse();
});
