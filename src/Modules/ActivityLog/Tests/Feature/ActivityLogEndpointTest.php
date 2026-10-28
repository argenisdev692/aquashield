<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Modules\ActivityLog\Models\ActivityEntry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['VIEW_ANY_ACTIVITY_LOGS', 'VIEW_ACTIVITY_LOGS', 'EXPORT_ACTIVITY_LOGS'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'VIEW_ANY_ACTIVITY_LOGS',
        'VIEW_ACTIVITY_LOGS',
        'EXPORT_ACTIVITY_LOGS',
    ]);
});

it('redirects guests to login', function (): void {
    $this->get('/activity')->assertRedirect('/login');
});

it('answers 401 to a guest JSON request', function (): void {
    $this->getJson('/activity')->assertUnauthorized();
});

it('forbids a user without VIEW_ANY_ACTIVITY_LOGS', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/activity')
        ->assertForbidden();
});

it('rejects an unknown filter with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/activity?filter[nope]=1')
        ->assertStatus(422)
        ->assertJsonValidationErrors('filter');
});

it('lists audit entries with a uuid and an allowlisted property set', function (): void {
    activity('backups')
        ->event('backup.quarantined')
        ->withProperties([
            'disk' => 'local',
            'filename' => '2026-10-01-00-00-00.zip',
            'size_bytes' => 1024,
            'secret' => 'must-not-leak',
        ])
        ->log('quarantined');

    $response = $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.event', 'backup.quarantined')
        ->assertJsonPath('rows.0.properties.filename', '2026-10-01-00-00-00.zip')
        ->assertJsonMissingPath('rows.0.properties.secret');

    expect($response->json('rows.0.uuid'))->toBeString()->not->toBe('');
});

it('filters by log name and search', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');
    activity('users')->event('user.deleted')->log('deleted');

    $this->actingAs($this->admin)
        ->getJson('/activity?filter[log_name]=users')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.event', 'user.deleted');

    $this->actingAs($this->admin)
        ->getJson('/activity?search=backup.quarantined')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.event', 'backup.quarantined');
});

it('renders the index page with generated props', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');

    $this->actingAs($this->admin)
        ->get('/activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ActivityLog/Index')
            ->has('rows', 1)
            ->where('canView', true)
            ->where('canExport', true));
});

it('shows one audit entry by uuid', function (): void {
    activity('backups')
        ->event('backup.quarantined')
        ->withProperties(['filename' => '2026-10-01-00-00-00.zip'])
        ->log('quarantined');

    $entry = ActivityEntry::query()->latest('id')->firstOrFail();

    $this->actingAs($this->admin)
        ->getJson("/activity/{$entry->uuid}")
        ->assertOk()
        ->assertJsonPath('activity.uuid', $entry->uuid)
        ->assertJsonPath('activity.event', 'backup.quarantined')
        ->assertJsonPath('activity.properties.filename', '2026-10-01-00-00-00.zip');

    $this->actingAs($this->admin)
        ->get("/activity/{$entry->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ActivityLog/Show')
            ->where('activity.uuid', $entry->uuid));
});

it('answers 404 for an unknown activity uuid', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/activity/'.Str::uuid7())
        ->assertNotFound();
});

it('forbids the show without VIEW_ACTIVITY_LOGS', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');

    $entry = ActivityEntry::query()->latest('id')->firstOrFail();

    $user = User::factory()->create();
    $user->givePermissionTo('VIEW_ANY_ACTIVITY_LOGS');

    $this->actingAs($user)
        ->getJson("/activity/{$entry->uuid}")
        ->assertForbidden();
});

it('exports the filtered audit trail as CSV, XLSX and PDF', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');
    activity('users')->event('user.deleted')->log('deleted');

    $csv = $this->actingAs($this->admin)
        ->get('/activity/export/csv?filter[log_name]=backups')
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('Recorded')
        ->toContain('backup.quarantined')
        ->not->toContain('user.deleted');

    $xlsx = $this->actingAs($this->admin)
        ->get('/activity/export/xlsx')
        ->assertOk()
        ->streamedContent();

    expect($xlsx)->toStartWith('PK');

    $pdf = $this->actingAs($this->admin)
        ->get('/activity/export/pdf')
        ->assertOk();

    expect($pdf->getContent())->toStartWith('%PDF');
});

it('forbids an export without EXPORT_ACTIVITY_LOGS', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['VIEW_ANY_ACTIVITY_LOGS', 'VIEW_ACTIVITY_LOGS']);

    $this->actingAs($user)
        ->get('/activity/export/csv')
        ->assertForbidden();
});

it('exposes a read-only REST API for the audit trail', function (): void {
    activity('backups')
        ->event('backup.quarantined')
        ->withProperties(['filename' => '2026-10-01-00-00-00.zip'])
        ->log('quarantined');

    $entry = ActivityEntry::query()->latest('id')->firstOrFail();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/activity')
        ->assertOk()
        ->assertJsonPath('rows.0.event', 'backup.quarantined');

    $this->getJson("/api/activity/{$entry->uuid}")
        ->assertOk()
        ->assertJsonPath('activity.uuid', $entry->uuid);
});

it('rejects the audit REST API without a token', function (): void {
    $this->getJson('/api/activity')->assertUnauthorized();
});

it('forbids the audit REST API without the permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/activity')->assertForbidden();
});
