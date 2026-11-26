<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Models\CompanyData;
use Shared\Domain\Enums\Role as RoleName;

uses(RefreshDatabase::class);

it('provisions the singleton for an active super admin', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $owner->assignRole(RoleName::SuperAdmin->value);

    $this->artisan('company-data:provision', ['--owner' => $owner->email])->assertSuccessful();

    $company = CompanyData::query()->first();

    expect($company)->not->toBeNull()
        ->and($company->user_id)->toBe($owner->id)
        ->and($company->company_name)->toBe('AquaShield Restoration USA');
});

it('uses the first active super admin when no owner is given', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $owner = User::factory()->create(['email' => 'first@example.com']);
    $owner->assignRole(RoleName::SuperAdmin->value);

    $this->artisan('company-data:provision')->assertSuccessful();

    expect(CompanyData::query()->first()?->user_id)->toBe($owner->id);
});

it('refuses an owner that is not an active super admin', function (): void {
    $owner = User::factory()->create(['email' => 'plain@example.com']);

    $this->artisan('company-data:provision', ['--owner' => $owner->email])->assertFailed();

    expect(CompanyData::query()->count())->toBe(0);
});
