<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Jobs\RunDatabaseBackupJob;
use Modules\Backups\Tests\Support\FakeBackupArchiveStore;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'VIEW_ANY_BACKUPS',
        'VIEW_BACKUPS',
        'DOWNLOAD_BACKUPS',
        'CREATE_BACKUPS',
        'DELETE_BACKUPS',
        'RESTORE_BACKUPS',
        'BULK_DELETE_BACKUPS',
        'BULK_RESTORE_BACKUPS',
        'EXPORT_BACKUPS',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo($permissions);

    $this->store = new FakeBackupArchiveStore;
    $this->app->instance(BackupArchiveStore::class, $this->store);
});

it('redirects guests to login', function (): void {
    $this->get('/backups')->assertRedirect('/login');
});

it('answers 401 to a guest JSON request', function (): void {
    $this->getJson('/backups')->assertUnauthorized();
});

it('forbids a user without VIEW_ANY_BACKUPS', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/backups')
        ->assertForbidden();
});

it('rejects an unknown filter with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/backups?filter[nope]=1')
        ->assertStatus(422)
        ->assertJsonValidationErrors('filter');
});

it('rejects an unknown sort column with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/backups?sort=-password')
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort_field');
});

it('lists archives newest first and marks the protected one', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $this->store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    $this->actingAs($this->admin)
        ->getJson('/backups')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('rows.0.filename', '2026-10-02-00-00-00.zip')
        ->assertJsonPath('rows.0.is_newest', true)
        ->assertJsonPath('rows.1.is_newest', false)
        ->assertJsonPath('can.create', true);
});

it('renders the index page with the generated props', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->actingAs($this->admin)
        ->get('/backups')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backups/Index')
            ->has('rows', 1)
            ->where('can.create', true)
            ->where('can.view', true));
});

it('shows one archive with its audit trail', function (): void {
    $archive = $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    activity('backups')
        ->event('backup.quarantined')
        ->withProperties(['disk' => 'local', 'filename' => $archive->filename])
        ->log('quarantined');

    $this->actingAs($this->admin)
        ->getJson("/backups/{$archive->uuid}")
        ->assertOk()
        ->assertJsonPath('backup.filename', $archive->filename)
        ->assertJsonPath('backup.is_newest', true)
        ->assertJsonPath('activities.0.event', 'backup.quarantined');

    $this->actingAs($this->admin)
        ->get("/backups/{$archive->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backups/Show')
            ->where('backup.filename', $archive->filename)
            ->has('activities', 1));
});

it('answers 404 for an unknown archive', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/backups/'.Str::uuid7())
        ->assertNotFound();
});

it('exposes a read-only REST API for backups', function (): void {
    $archive = $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/backups')
        ->assertOk()
        ->assertJsonPath('rows.0.filename', $archive->filename);

    $this->getJson("/api/backups/{$archive->uuid}")
        ->assertOk()
        ->assertJsonPath('backup.uuid', $archive->uuid);

    $this->get("/api/backups/{$archive->uuid}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

it('rejects the backup REST API without a token', function (): void {
    $this->getJson('/api/backups')->assertUnauthorized();
});

it('forbids the backup REST API without the permission', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/backups')->assertForbidden();
});

it('applies the search and pagination contract', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $this->store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    $this->actingAs($this->admin)
        ->getJson('/backups?search=2026-10-01')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.filename', '2026-10-01-00-00-00.zip');

    $this->actingAs($this->admin)
        ->getJson('/backups?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('page', 2)
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('rows.0.filename', '2026-10-01-00-00-00.zip');
});

it('quarantines an older archive instead of deleting it', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $newest = $this->store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    $this->actingAs($this->admin)
        ->deleteJson("/backups/{$newest->uuid}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuids');

    expect($this->store->active())->toHaveCount(2);

    $older = collect($this->store->active())
        ->firstWhere('filename', '2026-10-01-00-00-00.zip');

    $this->actingAs($this->admin)
        ->deleteJson("/backups/{$older->uuid}")
        ->assertNoContent();

    expect($this->store->active())->toHaveCount(1)
        ->and($this->store->trashed())->toHaveCount(1)
        ->and($this->store->trashed()[0]->filename)->toBe('2026-10-01-00-00-00.zip');
});

it('bulk deletes and bulk restores as a pair', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $this->store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $this->store->seedActive('2026-10-03-00-00-00.zip', '2026-10-03 00:00:00');

    $uuids = collect($this->store->active())
        ->reject(fn ($archive): bool => $archive->filename === '2026-10-03-00-00-00.zip')
        ->pluck('uuid')
        ->values()
        ->all();

    $this->actingAs($this->admin)
        ->postJson('/backups/bulk-delete', ['uuids' => $uuids])
        ->assertNoContent();

    expect($this->store->active())->toHaveCount(1)
        ->and($this->store->trashed())->toHaveCount(2);

    $trashed = collect($this->store->trashed())->pluck('uuid')->values()->all();

    $this->actingAs($this->admin)
        ->postJson('/backups/bulk-restore', ['uuids' => $trashed])
        ->assertNoContent();

    expect($this->store->active())->toHaveCount(3)
        ->and($this->store->trashed())->toHaveCount(0);
});

it('restores a single quarantined archive', function (): void {
    $this->store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $quarantined = $this->store->seedTrashed('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->actingAs($this->admin)
        ->patchJson("/backups/{$quarantined->uuid}/restore")
        ->assertNoContent();

    expect($this->store->active())->toHaveCount(2)
        ->and($this->store->trashed())->toHaveCount(0);
});

it('refuses a restore that would overwrite an active filename', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $quarantined = $this->store->seedTrashed('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->actingAs($this->admin)
        ->patchJson("/backups/{$quarantined->uuid}/restore")
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuids');
});

it('streams the archive to an authorized downloader', function (): void {
    $archive = $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $response = $this->actingAs($this->admin)
        ->get("/backups/{$archive->uuid}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    expect($response->streamedContent())->toBe($this->store->content);
});

it('answers 404 for a download of an unknown archive', function (): void {
    $this->actingAs($this->admin)
        ->get('/backups/'.Str::uuid7().'/download')
        ->assertNotFound();
});

it('forbids a download without DOWNLOAD_BACKUPS', function (): void {
    $archive = $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->actingAs(User::factory()->create())
        ->get("/backups/{$archive->uuid}/download")
        ->assertForbidden();
});

it('exports the filtered inventory as CSV, XLSX and PDF', function (): void {
    $this->store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $this->store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    $csv = $this->actingAs($this->admin)
        ->get('/backups/export/csv?search=2026-10-01')
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('Filename')
        ->toContain('2026-10-01-00-00-00.zip')
        ->not->toContain('2026-10-02-00-00-00.zip');

    $xlsx = $this->actingAs($this->admin)
        ->get('/backups/export/xlsx')
        ->assertOk()
        ->streamedContent();

    expect($xlsx)->toStartWith('PK');

    $pdf = $this->actingAs($this->admin)
        ->get('/backups/export/pdf')
        ->assertOk();

    expect($pdf->getContent())->toStartWith('%PDF');
});

it('forbids an export without EXPORT_BACKUPS', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/backups/export/csv')
        ->assertForbidden();
});

it('queues a database-only backup instead of running it in the request', function (): void {
    Queue::fake();

    $this->actingAs($this->admin)
        ->postJson('/backups/run')
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(RunDatabaseBackupJob::class);
});

it('forbids a manual run without CREATE_BACKUPS', function (): void {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->postJson('/backups/run')
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('returns only allowlisted activity properties', function (): void {
    activity('backups')
        ->event('backup.quarantined')
        ->withProperties([
            'disk' => 'local',
            'filename' => '2026-10-01-00-00-00.zip',
            'size_bytes' => 1024,
            'secret' => 'must-not-leak',
        ])
        ->log('quarantined');

    $this->actingAs($this->admin)
        ->getJson('/backups/activity')
        ->assertOk()
        ->assertJsonPath('rows.0.event', 'backup.quarantined')
        ->assertJsonPath('rows.0.properties.disk', 'local')
        ->assertJsonMissingPath('rows.0.properties.secret');
});
