<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CompanyData\Models\CompanyData;

uses(RefreshDatabase::class);

it('seeds roles, demo users and the company singleton with a demo password', function (): void {
    config(['seed.user_password' => 'AuditOnly-Temp-Password-123!']);

    $this->seed();

    $owner = User::query()->where('email', 'info@aquashieldrestorationusa.com')->first();
    $company = CompanyData::query()->first();

    expect($owner)->not->toBeNull()
        ->and($owner?->hasVerifiedEmail())->toBeTrue()
        ->and($company)->not->toBeNull()
        ->and($company?->user_id)->toBe($owner?->id);

    $userCount = User::query()->count();
    $companyCount = CompanyData::query()->count();

    $this->seed();

    expect(User::query()->count())->toBe($userCount)
        ->and(CompanyData::query()->count())->toBe($companyCount);
});

it('seeds reference data and skips demo data when the password is empty', function (): void {
    config(['seed.user_password' => null]);

    $this->seed();

    expect(User::query()->count())->toBe(0)
        ->and(CompanyData::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBeGreaterThan(0)
        ->and(Permission::query()->count())->toBeGreaterThan(0);
});
