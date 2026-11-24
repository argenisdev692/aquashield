<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

it('suspends and reactivates selected accounts in one transaction', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$first->uuid, $second->uuid],
            'status' => 'suspended',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 2)
        ->assertJsonCount(0, 'skipped');

    expect($first->refresh()->status->value)->toBe('suspended')
        ->and($second->refresh()->status->value)->toBe('suspended');

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$first->uuid, $second->uuid],
            'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 2);

    expect($first->refresh()->status->value)->toBe('active')
        ->and($second->refresh()->status->value)->toBe('active');
});

it('skips the actor and reports the reason instead of aborting the whole selection', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$this->admin->uuid, $target->uuid],
            'status' => 'suspended',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 1)
        ->assertJsonCount(1, 'skipped')
        ->assertJsonPath('skipped.0.reason', 'You cannot suspend your own account.');

    expect($this->admin->refresh()->status->value)->toBe('active')
        ->and($target->refresh()->status->value)->toBe('suspended');
});

it('cannot activate an invited account by bulk status', function (): void {
    $invited = User::factory()->invited()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$invited->uuid],
            'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 0)
        ->assertJsonCount(1, 'skipped');

    expect($invited->refresh()->status->value)->toBe('invited');
});

it('cannot suspend an invited account into an activation bypass', function (): void {
    $invited = User::factory()->invited()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', [
            'uuids' => [$invited->uuid],
            'status' => 'suspended',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 0)
        ->assertJsonCount(1, 'skipped');

    expect($invited->refresh()->status->value)->toBe('invited');
});

it('reassigns roles in bulk and skips the actor', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER']);
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-role', [
            'uuids' => [$this->admin->uuid, $target->uuid],
            'role' => 'MANAGER',
        ])
        ->assertOk()
        ->assertJsonPath('updated', 1)
        ->assertJsonCount(1, 'skipped');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($target->refresh()->hasRole('MANAGER'))->toBeTrue()
        ->and($this->admin->refresh()->hasRole('SUPER_ADMIN'))->toBeTrue();
});

it('resends only invited invitations and reports sent and skipped counts', function (): void {
    Queue::fake();

    $invited = User::factory()->invited()->create();
    $active = User::factory()->create();
    $oldTimestamp = $invited->invitation_sent_at?->getTimestamp();

    $this->travel(1)->seconds();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-invite', [
            'uuids' => [$invited->uuid, $active->uuid],
        ])
        ->assertOk()
        ->assertJsonPath('updated', 1)
        ->assertJsonCount(1, 'skipped');

    Queue::assertPushed(SendUserInvitationJob::class, 1);

    expect($invited->refresh()->invitation_sent_at?->getTimestamp())->not->toBe($oldTimestamp);
});

it('refuses every bulk endpoint to a delegated manager', function (): void {
    $manager = UserTestFixtures::delegatedAdmin();
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->postJson('/users/bulk-status', ['uuids' => [$target->uuid], 'status' => 'suspended'])
        ->assertForbidden();

    $this->actingAs($manager)
        ->postJson('/users/bulk-role', ['uuids' => [$target->uuid], 'role' => 'SUPER_ADMIN'])
        ->assertForbidden();

    $this->actingAs($manager)
        ->postJson('/users/bulk-invite', ['uuids' => [$target->uuid]])
        ->assertForbidden();

    $this->actingAs($manager)
        ->postJson('/users/bulk-delete', ['uuids' => [$target->uuid]])
        ->assertForbidden();

    $this->actingAs($manager)
        ->postJson('/users/bulk-restore', ['uuids' => [$target->uuid]])
        ->assertForbidden();

    expect($target->refresh()->status->value)->toBe('active');
    $this->assertNotSoftDeleted('users', ['uuid' => $target->uuid]);
});

it('validates the bulk payloads and unknown uuids with 422', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/users/bulk-status', ['uuids' => [], 'status' => 'invited'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['uuids', 'status']);

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-role', ['uuids' => [Str::uuid7()->toString()], 'role' => 'NOPE'])
        ->assertUnprocessable();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-invite', ['uuids' => [Str::uuid7()->toString()]])
        ->assertUnprocessable();
});

it('refuses a bulk delete that includes the actor and deletes nothing', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/users/bulk-delete', ['uuids' => [$this->admin->uuid, $target->uuid]])
        ->assertForbidden();

    $this->assertNotSoftDeleted('users', ['uuid' => $target->uuid]);
});
