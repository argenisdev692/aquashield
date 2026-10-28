<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Tests\Support\FakeBackupArchiveStore;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('purges quarantined archives past the retention window and audits each one', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('2026-01-01-00-00-00.zip', '2026-01-01 00:00:00');
    $store->seedTrashed('2026-10-27-00-00-00.zip', '2026-10-27 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);

    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.purged'
                && ($args[2]['filename'] ?? null) === '2026-01-01-00-00-00.zip');
    });

    $this->artisan('backups:purge-quarantine', ['--days' => 30])->assertSuccessful();

    expect($store->purged)->toBe(['2026-01-01-00-00-00.zip'])
        ->and($store->trashed())->toHaveCount(1);
});

it('lists candidates without deleting them on a dry run', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('2026-01-01-00-00-00.zip', '2026-01-01 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    $this->artisan('backups:purge-quarantine', ['--days' => 30, '--dry-run' => true])
        ->expectsOutputToContain('Would purge 2026-01-01-00-00-00.zip')
        ->assertSuccessful();

    expect($store->purged)->toBe([])
        ->and($store->trashed())->toHaveCount(1);
});

it('does nothing when the quarantine is inside the window', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedTrashed('2026-10-27-00-00-00.zip', '2026-10-27 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    $this->artisan('backups:purge-quarantine', ['--days' => 30])
        ->expectsOutputToContain('No quarantined backups')
        ->assertSuccessful();

    expect($store->purged)->toBe([]);
});
