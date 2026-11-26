<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CompanyDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Models\CompanyData;
use Shared\Domain\Enums\Role as RoleName;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates the singleton when the configured owner exists', function (): void {
    config(['seed.company_owner_email' => 'owner@aquashield.test']);

    $owner = User::factory()->create(['email' => 'owner@aquashield.test']);

    $this->seed(CompanyDataSeeder::class);

    $company = CompanyData::query()->first();

    expect($company)->not->toBeNull()
        ->and($company->company_name)->toBe('AquaShield Restoration USA')
        ->and($company->user_id)->toBe($owner->id)
        ->and($company->uuid)->toBeString()
        ->and($company->logo_path)->toBe('img/logo.webp');
});

it('skips the singleton without throwing when the demo owner does not exist', function (): void {
    $this->seed(CompanyDataSeeder::class);

    expect(CompanyData::query()->count())->toBe(0);
});

it('is a no-op when the singleton already exists without the demo owner', function (): void {
    $owner = User::factory()->create(['email' => 'existing-owner@example.com']);

    $company = new CompanyData;
    $company->fill(['company_name' => 'Existing profile']);
    $company->user_id = $owner->id;
    $company->save();

    $this->seed(CompanyDataSeeder::class);

    expect(CompanyData::query()->count())->toBe(1)
        ->and(CompanyData::query()->first()?->company_name)->toBe('Existing profile');
});

it('does not duplicate a provisioned singleton on a second run', function (): void {
    config(['seed.company_owner_email' => 'owner@aquashield.test']);

    User::factory()->create(['email' => 'owner@aquashield.test']);

    $this->seed(CompanyDataSeeder::class);
    $this->seed(CompanyDataSeeder::class);

    expect(CompanyData::query()->count())->toBe(1);
});

it('falls back to the first active super admin when the configured owner is absent', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['email' => 'real-admin@example.com']);
    $admin->assignRole(RoleName::SuperAdmin->value);

    config(['seed.company_owner_email' => 'missing@example.com']);

    $this->seed(CompanyDataSeeder::class);

    expect(CompanyData::query()->first()?->user_id)->toBe($admin->id);
});
