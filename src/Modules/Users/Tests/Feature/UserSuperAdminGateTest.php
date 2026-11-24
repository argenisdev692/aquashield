<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Users\Tests\Support\UserTestFixtures;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
    $this->target = User::factory()->create();
});

/**
 * A delegated admin holds every Users permission but not SUPER_ADMIN.
 * The `can:manage-staff` gate must answer 403 before validation, before the
 * policy and before any controller logic.
 */
it('returns 403 to a non-super-admin on every staff management surface', function (): void {
    $manager = UserTestFixtures::delegatedAdmin();

    $requests = [
        fn () => $this->actingAs($manager)->getJson('/users'),
        fn () => $this->actingAs($manager)->getJson('/users/'.$this->target->uuid),
        fn () => $this->actingAs($manager)->getJson('/users/'.$this->target->uuid.'/edit'),
        fn () => $this->actingAs($manager)->getJson('/users/create'),
        fn () => $this->actingAs($manager)->getJson('/users/access-catalog'),
        fn () => $this->actingAs($manager)->getJson('/users/availability?field=email&value=x@gmail.com'),
        fn () => $this->actingAs($manager)->getJson('/users/export/csv'),
        fn () => $this->actingAs($manager)->postJson('/users', [
            'first_name' => 'Nope',
            'email' => 'nope@gmail.com',
        ]),
        fn () => $this->actingAs($manager)->putJson('/users/'.$this->target->uuid, [
            'first_name' => 'Nope',
            'email' => $this->target->email,
        ]),
        fn () => $this->actingAs($manager)->deleteJson('/users/'.$this->target->uuid),
        fn () => $this->actingAs($manager)->postJson('/users/bulk-status', [
            'uuids' => [$this->target->uuid],
            'status' => 'suspended',
        ]),
        fn () => $this->actingAs($manager)->postJson('/users/bulk-role', [
            'uuids' => [$this->target->uuid],
            'role' => 'SUPER_ADMIN',
        ]),
        fn () => $this->actingAs($manager)->postJson('/users/bulk-invite', [
            'uuids' => [$this->target->uuid],
        ]),
        fn () => $this->actingAs($manager)->postJson('/users/bulk-delete', [
            'uuids' => [$this->target->uuid],
        ]),
        fn () => $this->actingAs($manager)->postJson('/users/bulk-restore', [
            'uuids' => [$this->target->uuid],
        ]),
        fn () => $this->actingAs($manager)->patchJson('/users/'.$this->target->uuid.'/restore'),
        fn () => $this->actingAs($manager)->postJson('/users/'.$this->target->uuid.'/invitation'),
        fn () => $this->actingAs($manager)->postJson('/users/'.$this->target->uuid.'/password-reset'),
        fn () => $this->actingAs($manager)->putJson('/users/'.$this->target->uuid.'/roles', [
            'roles' => [],
        ]),
        fn () => $this->actingAs($manager)->putJson('/users/'.$this->target->uuid.'/permissions', [
            'permissions' => [],
        ]),
    ];

    foreach ($requests as $request) {
        $request()->assertForbidden();
    }

    expect($this->target->refresh()->status->value)->toBe('active');
    $this->assertNotSoftDeleted('users', ['uuid' => $this->target->uuid]);
});

it('returns 401 to a guest on the JSON management surface', function (): void {
    $this->getJson('/users')->assertUnauthorized();
    $this->postJson('/users', [])->assertUnauthorized();
    $this->getJson('/users/availability?field=email&value=x@gmail.com')->assertUnauthorized();
    $this->postJson('/users/bulk-restore', [])->assertUnauthorized();
    $this->postJson('/users/'.Str::uuid7()->toString().'/invitation')->assertUnauthorized();
});

it('allows the super admin through the same matrix', function (): void {
    $this->actingAs($this->admin)->getJson('/users')->assertOk();
    $this->actingAs($this->admin)->getJson('/users/'.$this->target->uuid)->assertOk();
    $this->actingAs($this->admin)->getJson('/users/availability?field=email&value=x@gmail.com')->assertOk();
});

it('forbids a super admin who lacks the fine-grained permissions', function (): void {
    $role = Role::findByName(RoleName::SuperAdmin->value, 'web');
    $role->revokePermissionTo(PermissionName::ViewAnyUser->value);
    $role->revokePermissionTo(PermissionName::CreateUser->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->getJson('/users')
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->postJson('/users', ['first_name' => 'Nope', 'email' => 'nope@gmail.com'])
        ->assertForbidden();
});
