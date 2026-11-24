<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Users\Actions\AssignRolesAction;
use Modules\Users\Enums\UserStatus;
use Modules\Users\Policies\UserPolicy;
use Modules\Users\Support\AccessCeiling;
use Modules\Users\Tests\Support\UserTestFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
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
    $target->givePermissionTo(PermissionName::ExportUser->value);

    expect($this->ceiling->canManage($superAdmin, $target))->toBeTrue();
});

it('scopes the view policy to the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', [PermissionName::ViewUser->value]);
    $manager = UserTestFixtures::userWithRole('MANAGER');
    $target = User::factory()->create();
    $target->givePermissionTo(PermissionName::DeleteUser->value);

    $policy = app(UserPolicy::class);

    expect($policy->view($manager, $target))->toBeFalse();
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

it('protects the last active super admin and releases the guard once another exists', function (): void {
    $first = UserTestFixtures::superAdmin();

    expect($this->ceiling->isLastActiveSuperAdmin($first))->toBeTrue();

    UserTestFixtures::superAdmin();

    expect($this->ceiling->isLastActiveSuperAdmin($first))->toBeFalse();
});

it('does not count a suspended or invited super admin as a usable administrator', function (): void {
    $active = UserTestFixtures::superAdmin();
    $suspended = UserTestFixtures::superAdmin();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();

    expect($this->ceiling->isLastActiveSuperAdmin($active))->toBeTrue()
        ->and($this->ceiling->activeSuperAdminCount())->toBe(1);
});

it('refuses deleting or suspending the last active super admin from any actor', function (): void {
    $actor = UserTestFixtures::superAdmin();
    $actor->forceFill(['status' => UserStatus::Suspended])->save();
    $target = UserTestFixtures::superAdmin();

    $policy = app(UserPolicy::class);

    expect($policy->delete($actor, $target))->toBeFalse()
        ->and($policy->suspend($actor, $target))->toBeFalse();
});

it('refuses demoting the last active super admin in the role action', function (): void {
    $actor = UserTestFixtures::superAdmin();
    $actor->forceFill(['status' => UserStatus::Suspended])->save();
    $target = UserTestFixtures::superAdmin();

    expect(fn () => app(AssignRolesAction::class)
        ->execute($target, $actor, ['MANAGER']))
        ->toThrow(ValidationException::class);

    expect($target->refresh()->hasRole('SUPER_ADMIN'))->toBeTrue();
});
