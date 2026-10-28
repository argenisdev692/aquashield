<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Users\Support\AccessCeiling;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->ceiling = app(AccessCeiling::class);
});

it('compares effective permissions, not role names', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    $target = User::factory()->create();
    $target->givePermissionTo('VIEW_USER');
    $target->refresh();

    expect($this->ceiling->canManage($manager, $target))->toBeTrue();

    $target->givePermissionTo('DELETE_USER');
    $target->refresh();

    expect($this->ceiling->canManage($manager, $target))->toBeFalse();
});

it('lets a super admin manage anyone', function (): void {
    $superAdmin = UserTestFixtures::superAdmin();
    $target = User::factory()->create();
    $target->givePermissionTo('EXPORT_USER');

    expect($this->ceiling->canManage($superAdmin, $target))->toBeTrue();
});

it('refuses a role that carries a permission the actor lacks', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER', 'ASSIGN_ROLES_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    UserTestFixtures::role('ADMIN', ['EXPORT_USER']);

    expect(fn () => $this->ceiling->assertCanGrantRoles($manager, ['ADMIN']))
        ->toThrow(ValidationException::class);
});

it('allows a role whose permissions the actor holds', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER', 'ASSIGN_ROLES_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    UserTestFixtures::role('VIEWER', ['VIEW_USER']);

    $this->ceiling->assertCanGrantRoles($manager, ['VIEWER']);

    expect(true)->toBeTrue();
});

it('refuses a direct permission the actor lacks and allows one they hold', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER', 'ASSIGN_PERMISSIONS_USER']);
    $manager = UserTestFixtures::userWithRole('MANAGER');

    $this->ceiling->assertCanGrantPermissions($manager, ['VIEW_USER']);

    expect(fn () => $this->ceiling->assertCanGrantPermissions($manager, ['DELETE_USER']))
        ->toThrow(ValidationException::class);
});

it('protects the last super admin and releases the guard once another exists', function (): void {
    $first = UserTestFixtures::superAdmin();

    expect($this->ceiling->isLastSuperAdmin($first))->toBeTrue();

    UserTestFixtures::superAdmin();

    expect($this->ceiling->isLastSuperAdmin($first))->toBeFalse();
});
