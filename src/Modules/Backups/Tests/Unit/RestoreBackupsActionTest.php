<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\Backups\Application\Actions\RestoreBackupsAction;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Tests\Support\FakeBackupArchiveStore;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('restores quarantined archives and audits them', function (): void {
    $store = new FakeBackupArchiveStore;
    $archive = $store->seedTrashed('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class, function (MockInterface $mock) use ($archive): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.restored'
                && ($args[2]['filename'] ?? null) === $archive->filename);
    });

    app(RestoreBackupsAction::class)->execute([$archive->uuid], null);

    expect($store->active())->toHaveCount(1)
        ->and($store->trashed())->toBe([]);
});

it('rolls back the restores it already made when a later restore fails', function (): void {
    $store = new FakeBackupArchiveStore;
    $first = $store->seedTrashed('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $second = $store->seedTrashed('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $store->failRestoreFor = $second->filename;

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    expect(fn () => app(RestoreBackupsAction::class)->execute([$first->uuid, $second->uuid], null))
        ->toThrow(RuntimeException::class);

    // Both archives are back in quarantine; nothing is active.
    expect($store->trashed())->toHaveCount(2)
        ->and($store->active())->toBe([]);
});

it('does not fail the restore when the audit write fails', function (): void {
    $store = new FakeBackupArchiveStore;
    $archive = $store->seedTrashed('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('log')->once()->andThrow(new RuntimeException('audit down'));
    });

    app(RestoreBackupsAction::class)->execute([$archive->uuid], null);

    expect($store->active())->toHaveCount(1)
        ->and($store->trashed())->toBe([]);
});
