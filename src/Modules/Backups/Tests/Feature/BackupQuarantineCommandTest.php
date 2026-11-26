<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupDestinationUnavailable;
use Modules\Backups\Tests\Support\FakeBackupArchiveStore;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Fixtures are relative to `now()`, not fixed dates: the command's decision is
 * "older than N days", so a hard-coded date silently changes meaning as the
 * calendar moves (2026-10-27 was inside a 30-day window on 2026-10-28 and
 * outside it on 2026-11-26).
 */

it('purges quarantined archives past the retention window and audits each one', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('old.zip', now()->subDays(60)->format('Y-m-d H:i:s'));
    $store->seedTrashed('recent.zip', now()->subDays(2)->format('Y-m-d H:i:s'));

    $this->app->instance(BackupArchiveStore::class, $store);

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.purged'
                && ($args[2]['filename'] ?? null) === 'old.zip');
    });

    $this->artisan('backups:purge-quarantine', ['--days' => 30])->assertSuccessful();

    expect($store->purged)->toBe(['old.zip'])
        ->and($store->trashed())->toHaveCount(1);
});

it('lists candidates without deleting them on a dry run', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('old.zip', now()->subDays(60)->format('Y-m-d H:i:s'));

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    $this->artisan('backups:purge-quarantine', ['--days' => 30, '--dry-run' => true])
        ->expectsOutputToContain('Would purge old.zip')
        ->assertSuccessful();

    expect($store->purged)->toBe([])
        ->and($store->trashed())->toHaveCount(1);
});

it('does nothing when the quarantine is inside the window', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('recent.zip', now()->subDays(2)->format('Y-m-d H:i:s'));

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    $this->artisan('backups:purge-quarantine', ['--days' => 30])
        ->expectsOutputToContain('No quarantined backups')
        ->assertSuccessful();

    expect($store->purged)->toBe([]);
});

it('refuses a non-positive retention override', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('old.zip', now()->subDays(60)->format('Y-m-d H:i:s'));

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    $this->artisan('backups:purge-quarantine', ['--days' => 0])
        ->expectsOutputToContain('positive integer')
        ->assertFailed();

    expect($store->purged)->toBe([]);
});

it('refuses a fractional retention override', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('old.zip', now()->subDays(60)->format('Y-m-d H:i:s'));

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    $this->artisan('backups:purge-quarantine', ['--days' => '1.5'])
        ->expectsOutputToContain('positive integer')
        ->assertFailed();

    expect($store->purged)->toBe([]);
});

it('refuses to purge in production without confirmation', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('old.zip', now()->subDays(60)->format('Y-m-d H:i:s'));

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');
    $this->app['env'] = 'production';

    $this->artisan('backups:purge-quarantine', ['--days' => 30])
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect($store->purged)->toBe([]);
});

it('fails when the quarantine destination is unreachable', function (): void {
    $this->mock(BackupArchiveStore::class, function (MockInterface $mock): void {
        $mock->shouldReceive('trashed')->once()->andThrow(BackupDestinationUnavailable::allDisks());
    });

    $this->artisan('backups:purge-quarantine', ['--days' => 30])
        ->expectsOutputToContain('unreachable')
        ->assertFailed();
});

it('purges the rest and fails when one archive cannot be removed', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('old-a.zip', now()->subDays(60)->format('Y-m-d H:i:s'));
    $store->seedTrashed('old-b.zip', now()->subDays(60)->format('Y-m-d H:i:s'));
    $store->failPurgeFor = 'old-a.zip';

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.purged'
                && ($args[2]['filename'] ?? null) === 'old-b.zip');
    });

    $this->artisan('backups:purge-quarantine', ['--days' => 30])
        ->expectsOutputToContain('could not be purged')
        ->assertFailed();

    expect($store->purged)->toBe(['old-b.zip'])
        ->and(collect($store->trashed())->pluck('filename')->all())->toBe(['old-a.zip']);
});
