<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\ActivityLog\Exports\ActivityExportTransformer;
use Modules\ActivityLog\Models\ActivityEntry;
use Shared\Domain\Ports\AuditPort;
use Spatie\Activitylog\Models\Activity;
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

it('rejects a scalar filter instead of silently widening the list', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/activity?filter=backups')
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
            'requested_by' => 7,
            'secret' => 'must-not-leak',
        ])
        ->log('quarantined');

    $response = $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.event', 'backup.quarantined')
        ->assertJsonPath('rows.0.properties.filename', '2026-10-01-00-00-00.zip')
        ->assertJsonMissingPath('rows.0.properties.requested_by')
        ->assertJsonMissingPath('rows.0.properties.secret');

    expect($response->json('rows.0.uuid'))->toBeString()->not->toBe('');
});

it('projects the security-relevant properties of access-control and user events', function (): void {
    activity('access-control')->event('role.updated')->withProperties([
        'name' => 'Manager',
        'previous_name' => 'Supervisor',
        'permissions_added' => ['VIEW_USERS'],
        'permissions_removed' => ['EXPORT_USERS'],
        'secret' => 'must-not-leak',
    ])->log('role.updated');

    activity('users')->event('user.status.changed')->withProperties([
        'from' => 'active',
        'to' => 'suspended',
        'email' => 'must-not-leak@example.test',
    ])->log('user.status.changed');

    activity('auth')->event('auth.failed')->withProperties([
        'guard' => 'web',
        'email' => 'must-not-leak@example.test',
        'ip_address' => '127.0.0.1',
    ])->log('auth.failed');

    $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('rows.0.properties.guard', 'web')
        ->assertJsonMissingPath('rows.0.properties.email')
        ->assertJsonMissingPath('rows.0.properties.ip_address')
        ->assertJsonPath('rows.1.properties.from', 'active')
        ->assertJsonPath('rows.1.properties.to', 'suspended')
        ->assertJsonMissingPath('rows.1.properties.email')
        ->assertJsonPath('rows.2.properties.name', 'Manager')
        ->assertJsonPath('rows.2.properties.previous_name', 'Supervisor')
        ->assertJsonPath('rows.2.properties.permissions_added.0', 'VIEW_USERS')
        ->assertJsonPath('rows.2.properties.permissions_removed.0', 'EXPORT_USERS')
        ->assertJsonMissingPath('rows.2.properties.secret');
});

it('projects user changes and export metadata without the filter payload', function (): void {
    activity('users')->event('user.updated')->withProperties([
        'changed' => ['email'],
        'email' => 'must-not-leak@example.test',
    ])->log('user.updated');

    activity('users')->event('user.exported')->withProperties([
        'format' => 'csv',
        'rows' => 2,
        'filters' => ['search' => 'ada'],
    ])->log('user.exported');

    $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('rows.0.properties.format', 'csv')
        ->assertJsonPath('rows.0.properties.rows', 2)
        ->assertJsonMissingPath('rows.0.properties.filters')
        ->assertJsonPath('rows.1.properties.changed.0', 'email')
        ->assertJsonMissingPath('rows.1.properties.email');
});

it('returns ISO 8601 timestamps with an explicit offset', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');

    $response = $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk();

    expect($response->json('rows.0.created_at'))
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');
});

it('keeps the causer name after the actor is soft deleted', function (): void {
    $actor = User::factory()->create();

    activity('users')->causedBy($actor)->event('user.updated')->log('updated');
    $actor->delete();

    $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('rows.0.causer_name', $actor->name);
});

it('audits detail views only when the audit_views switch is enabled', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');

    $entry = ActivityEntry::query()->firstOrFail();

    $this->actingAs($this->admin)->getJson("/activity/{$entry->uuid}")->assertOk();

    expect(Activity::query()->where('event', 'activity.viewed')->count())->toBe(0);

    config()->set('activitylog.audit_views', true);

    $this->actingAs($this->admin)->getJson("/activity/{$entry->uuid}")->assertOk();

    $viewed = Activity::query()->where('event', 'activity.viewed')->sole();

    expect(data_get($viewed->properties, 'entry'))->toBe($entry->uuid);
});

it('paginates audit entries with a hard per-page cap', function (): void {
    foreach (range(1, 16) as $i) {
        activity('backups')->event('backup.quarantined')->log('entry '.$i);
    }

    $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('total', 16)
        ->assertJsonPath('perPage', 15)
        ->assertJsonCount(15, 'rows');

    $this->actingAs($this->admin)
        ->getJson('/activity?per_page=100')
        ->assertOk()
        ->assertJsonCount(16, 'rows');

    $this->actingAs($this->admin)
        ->getJson('/activity?per_page=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

it('filters by subject type and id', function (): void {
    $subject = User::factory()->create();

    activity('company-data')
        ->performedOn($subject)
        ->event('financial-fields-updated')
        ->withProperties(['changed' => 'bank_iban'])
        ->log('updated');

    activity('backups')->event('backup.quarantined')->log('quarantined');

    $this->actingAs($this->admin)
        ->getJson('/activity?'.http_build_query([
            'filter' => [
                'subject_type' => User::class,
                'subject_id' => $subject->id,
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('rows.0.event', 'financial-fields-updated')
        ->assertJsonPath('rows.0.properties.changed', 'bank_iban');
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

it('answers 404 for a malformed activity uuid before the query', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/activity/not-a-uuid')
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

it('caps the PDF export at the renderer-safe bound but still streams CSV', function (): void {
    foreach (range(1, ActivityExportTransformer::MAX_PDF_ROWS + 1) as $i) {
        activity('backups')->event('backup.quarantined')->log('entry '.$i);
    }

    // DomPDF renders synchronously; a large PDF is a memory/worker risk. The
    // streamed formats keep the higher MAX_ROWS bound.
    $this->actingAs($this->admin)
        ->get('/activity/export/pdf')
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->get('/activity/export/csv')
        ->assertOk();
});

it('neutralises spreadsheet formula injection in the CSV export', function (): void {
    activity('backups')->event('backup.quarantined')->log('=HYPERLINK("https://evil.test","open")');

    $csv = $this->actingAs($this->admin)
        ->get('/activity/export/csv')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");
});

it('exports the same causer snapshot the list shows', function (): void {
    $actor = User::factory()->create();
    $actor->forceFill(['first_name' => 'Ada', 'last_name' => 'Lovelace'])->save();

    app(AuditPort::class)->log('user.updated', $actor, [], $actor, 'users');

    $actor->forceFill(['first_name' => 'Grace', 'last_name' => 'Hopper'])->save();

    $csv = $this->actingAs($this->admin)
        ->get('/activity/export/csv')
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('Ada Lovelace')
        ->not->toContain('Grace Hopper');
});

it('forbids an export without EXPORT_ACTIVITY_LOGS', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['VIEW_ANY_ACTIVITY_LOGS', 'VIEW_ACTIVITY_LOGS']);

    $this->actingAs($user)
        ->get('/activity/export/csv')
        ->assertForbidden();
});

it('forbids an export for a user who may export but not list', function (): void {
    $exportOnly = User::factory()->create();
    $exportOnly->givePermissionTo('EXPORT_ACTIVITY_LOGS');

    // EXPORT_ACTIVITY_LOGS must never widen a list the actor cannot read.
    $this->actingAs($exportOnly)
        ->get('/activity/export/csv')
        ->assertForbidden();
});

it('audits an activity export without storing free-text filter values', function (): void {
    $this->actingAs($this->admin)
        ->get('/activity/export/csv?search=ada&filter[event]=ada&filter[log_name]=backups')
        ->assertOk();

    $entry = Activity::query()->where('event', 'activity.exported')->sole();

    expect($entry->log_name)->toBe('activity')
        ->and(data_get($entry->properties, 'filters.has_search'))->toBeTrue()
        ->and(data_get($entry->properties, 'filters.has_event_filter'))->toBeTrue()
        ->and(data_get($entry->properties, 'filters.has_log_name_filter'))->toBeTrue()
        ->and($entry->properties->toArray())->not->toHaveKey('search')
        ->and($entry->properties->toArray()['filters'])->not->toHaveKey('search')
        ->and($entry->properties->toArray()['filters'])->not->toHaveKey('event')
        ->and($entry->properties->toArray()['filters'])->not->toHaveKey('log_name');
});

it('rejects a non-numeric subject id with 422', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/activity?filter[subject_id]=abc')
        ->assertStatus(422);
});

it('searches case-insensitively on the production database engine', function (): void {
    activity('backups')->event('backup.quarantined')->log('Quarantined by operator');

    $this->actingAs($this->admin)
        ->getJson('/activity?search=BACKUP.QUARANTINED')
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($this->admin)
        ->getJson('/activity?filter[event]=QUARANTINED')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('applies the date window in the viewer timezone', function (): void {
    DB::table('activity_log')->insert([
        'uuid' => (string) Str::uuid7(),
        'log_name' => 'backups',
        'event' => 'backup.quarantined',
        'description' => 'boundary',
        'created_at' => '2026-11-26 02:00:00',
        'updated_at' => '2026-11-26 02:00:00',
    ]);

    // 2026-11-26 02:00 UTC is 2026-11-25 20:00 in America/Chicago.
    $this->actingAs($this->admin)
        ->getJson('/activity?'.http_build_query([
            'filter' => [
                'date_from' => '2026-11-25',
                'date_to' => '2026-11-25',
                'timezone' => 'America/Chicago',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1);

    // The same calendar day without the zone is the UTC day, which excludes it.
    $this->actingAs($this->admin)
        ->getJson('/activity?'.http_build_query([
            'filter' => [
                'date_from' => '2026-11-25',
                'date_to' => '2026-11-25',
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('counts the unfiltered table once per cache window, not once per page', function (): void {
    activity('backups')->log('one');

    $counts = 0;

    DB::listen(function ($query) use (&$counts): void {
        if (str_contains($query->sql, 'count(*)')) {
            $counts++;
        }
    });

    $this->actingAs($this->admin)->getJson('/activity')->assertOk();
    $this->actingAs($this->admin)->getJson('/activity')->assertOk();

    expect($counts)->toBe(1);
});

it('snapshots the causer name so a later rename does not rewrite history', function (): void {
    $actor = User::factory()->create();
    $actor->forceFill(['first_name' => 'Ada', 'last_name' => 'Lovelace'])->save();

    app(AuditPort::class)->log('user.updated', $actor, [], $actor, 'users');

    $actor->forceFill(['first_name' => 'Grace', 'last_name' => 'Hopper'])->save();

    $this->actingAs($this->admin)
        ->getJson('/activity')
        ->assertOk()
        ->assertJsonPath('rows.0.causer_name', 'Ada Lovelace')
        ->assertJsonMissingPath('rows.0.properties.causer_name');
});

it('serializes empty properties as a JSON object, not an array', function (): void {
    activity('backups')->event('backup.quarantined')->log('quarantined');

    $response = $this->actingAs($this->admin)->getJson('/activity')->assertOk();

    expect($response->getContent())->toContain('"properties":{}');
});
