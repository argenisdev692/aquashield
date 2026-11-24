<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Jobs\SendUserInvitationJob;
use Modules\Users\Tests\Support\UserTestFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    UserTestFixtures::seedPermissions();
    $this->admin = UserTestFixtures::superAdmin();
});

/* ------------------------------------------------------------------ index */

it('renders the users index with the first page and its filter contract', function (): void {
    $target = User::factory()->create([
        'first_name' => 'Juana',
        'last_name' => 'Perez',
        'email' => 'juana@gmail.com',
    ]);

    $this->actingAs($this->admin)
        ->get('/users?search=Juana&sort=email')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('rows', 1)
            ->where('rows.0.uuid', $target->uuid)
            ->where('total', 1)
            ->where('currentPage', 1)
            ->where('perPage', 15)
            ->where('filters.search', 'Juana')
            ->where('filters.sort_field', 'email')
            ->where('filters.sort_order', 1)
            ->has('catalog.roles', 1)
            ->where('catalog.roles.0.name', 'SUPER_ADMIN')
            ->where('rows.0.is_super_admin', false)
            ->where('canCreate', true)
            ->where('canExport', true)
            ->where('canBulkDelete', true)
            ->where('canBulkRestore', true)
            ->where(
                'authPermissions',
                fn (Collection $permissions) => $permissions->contains('VIEW_ANY_USER'),
            )
        );
});

it('forbids a non-super-admin from the users index even with every Users permission', function (): void {
    $viewer = UserTestFixtures::delegatedAdmin();

    $this->actingAs($viewer)
        ->get('/users')
        ->assertForbidden();
});

it('keeps the JSON list contract for data-table clients', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/users')
        ->assertOk()
        ->assertJsonStructure(['rows', 'total', 'page', 'per_page']);
});

it('renders the create screen with the access catalog', function (): void {
    $this->actingAs($this->admin)
        ->get('/users/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Create')
            ->has('catalog.roles', 1)
            ->has('catalog.permissions')
            ->has('googleMapsApiKey')
        );
});

it('forbids the create screen without CREATE_USER', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('VIEW_ANY_USER');

    $this->actingAs($viewer)->get('/users/create')->assertForbidden();
});

/* ------------------------------------------------------------------- show */

it('renders the show screen with row-level abilities', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->get('/users/'.$target->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Show')
            ->where('user.uuid', $target->uuid)
            ->where('user.is_super_admin', false)
            ->where('canUpdate', true)
            ->where('canDelete', true)
            ->where('canRestore', true)
            ->where('canAssignRoles', true)
            ->where('canAssignPermissions', true)
            ->where('canSendPasswordReset', true)
            ->where('canResendInvitation', false)
            ->where('invitationExpiresInHours', 48)
            ->where('passwordResetExpiresInMinutes', 60)
            ->has('catalog.roles', 1)
        );
});

it('marks a super administrator through a server capability flag', function (): void {
    $this->actingAs($this->admin)
        ->get('/users/'.$this->admin->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Show')
            ->where('user.is_super_admin', true)
        );
});

it('renders a soft-deleted user so restore stays reachable', function (): void {
    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($this->admin)
        ->get('/users/'.$target->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Show')
            ->where('user.uuid', $target->uuid)
            ->whereNot('user.deleted_at', null)
            ->where('canRestore', true)
        );
});

it('exposes exactly one access-link capability per lifecycle state', function (): void {
    $active = User::factory()->create();
    $invited = User::factory()->invited()->create();
    $suspended = User::factory()->suspended()->create();
    $deleted = User::factory()->create();
    $deleted->delete();

    $this->actingAs($this->admin)
        ->get('/users/'.$active->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSendPasswordReset', true)
            ->where('canResendInvitation', false)
        );

    $this->actingAs($this->admin)
        ->get('/users/'.$invited->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSendPasswordReset', false)
            ->where('canResendInvitation', true)
        );

    $this->actingAs($this->admin)
        ->get('/users/'.$suspended->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSendPasswordReset', false)
            ->where('canResendInvitation', false)
        );

    $this->actingAs($this->admin)
        ->get('/users/'.$deleted->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSendPasswordReset', false)
            ->where('canResendInvitation', false)
        );

    $deleted->restore();

    $this->actingAs($this->admin)
        ->get('/users/'.$deleted->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSendPasswordReset', true)
            ->where('canResendInvitation', false)
        );
});

it('renders the edit screen for a manageable user', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->get('/users/'.$target->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Edit')
            ->where('user.uuid', $target->uuid)
            ->has('googleMapsApiKey')
        );
});

it('forbids editing a user above the actor ceiling', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_ANY_USER', 'VIEW_USER', 'UPDATE_USER']);
    UserTestFixtures::role('ADMIN', ['DELETE_USER']);

    $manager = UserTestFixtures::userWithRole('MANAGER');
    $target = UserTestFixtures::userWithRole('ADMIN');

    $this->actingAs($manager)
        ->get('/users/'.$target->uuid.'/edit')
        ->assertForbidden();
});

/* ------------------------------------------------------------------ write */

it('persists and exposes the address projection', function (): void {
    Queue::fake();

    $this->actingAs($this->admin)
        ->postJson('/users', [
            'first_name' => 'Ada',
            'email' => 'ada@gmail.com',
            'address' => '1 Main St',
            'address_2' => 'Apt 4',
            'zip_code' => '77002',
            'city' => 'Houston',
            'state' => 'TX',
            'country' => 'US',
            'latitude' => 29.7604,
            'longitude' => -95.3698,
        ])
        ->assertCreated()
        ->assertJsonPath('address', '1 Main St')
        ->assertJsonPath('address_2', 'Apt 4')
        ->assertJsonPath('zip_code', '77002')
        ->assertJsonPath('latitude', 29.7604)
        ->assertJsonPath('longitude', -95.3698);

    $user = User::query()->where('email', 'ada@gmail.com')->firstOrFail();

    $this->actingAs($this->admin)
        ->get('/users/'.$user->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Show')
            ->where('user.address', '1 Main St')
            ->where('user.address_2', 'Apt 4')
            ->where('user.zip_code', '77002')
            ->where('user.latitude', 29.7604)
            ->where('user.longitude', -95.3698)
        );
});

it('redirects an Inertia create to the new user page and queues the invitation', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->admin)
        ->post('/users', [
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'juan@gmail.com',
        ]);

    $user = User::query()->where('email', 'juan@gmail.com')->firstOrFail();

    $response->assertRedirect(route('users.show', ['user' => $user->uuid]));
    Queue::assertPushed(SendUserInvitationJob::class);

    $this->actingAs($this->admin)
        ->get(route('users.show', ['user' => $user->uuid]))
        ->assertOk();

    expect($user->status->value)->toBe('invited')
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('redirects Inertia validation errors back with the error bag', function (): void {
    $this->actingAs($this->admin)
        ->from('/users/create')
        ->post('/users', [])
        ->assertRedirect('/users/create')
        ->assertSessionHasErrors(['first_name', 'email']);
});

it('redirects an Inertia profile update back to the show screen', function (): void {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->put('/users/'.$target->uuid, [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => $target->email,
        ])
        ->assertRedirect(route('users.show', ['user' => $target->uuid]));

    expect($target->refresh()->first_name)->toBe('Updated');
});

it('redirects an Inertia access assignment back to the show screen', function (): void {
    UserTestFixtures::role('MANAGER', ['VIEW_USER']);
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->put('/users/'.$target->uuid.'/roles', ['roles' => ['MANAGER']])
        ->assertRedirect(route('users.show', ['user' => $target->uuid]));

    expect($target->refresh()->hasRole('MANAGER'))->toBeTrue();
});
