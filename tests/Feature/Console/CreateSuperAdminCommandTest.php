<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Shared\Domain\Enums\Role as RoleName;

uses(RefreshDatabase::class);

it('creates an active super admin that must change its password', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->artisan('app:create-super-admin', [
        '--email' => 'Root@Example.com',
        '--first-name' => 'Root',
        '--password' => 'Password!123',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'root@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
        ->and($user->isActive())->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('Password!123', (string) $user->password))->toBeTrue();
});

it('refuses to create the admin before the role catalogue exists', function (): void {
    $this->artisan('app:create-super-admin', [
        '--email' => 'root@example.com',
        '--password' => 'Password!123',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
