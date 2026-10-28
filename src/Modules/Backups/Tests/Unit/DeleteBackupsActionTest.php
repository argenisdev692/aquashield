<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Mockery\MockInterface;
use Modules\Backups\Application\Actions\DeleteBackupsAction;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupDeletionRefused;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Tests\Support\FakeBackupArchiveStore;
use Shared\Domain\Ports\AuditPort;
use Tests\TestCase;

uses(TestCase::class);

it('quarantines an older archive and audits it without the database', function (): void {
    $store = new FakeBackupArchiveStore;
    $newest = $store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $older = $store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);

    $this->mock(AuditPort::class, function (MockInterface $mock) use ($older): void {
        $mock->shouldReceive('log')
            ->once()
            ->withArgs(fn (...$args): bool => $args[0] === 'backup.quarantined'
                && ($args[2]['filename'] ?? null) === $older->filename);
    });

    app(DeleteBackupsAction::class)->execute([$older->uuid], null);

    expect($store->active())->toHaveCount(1)
        ->and($store->active()[0]->filename)->toBe($newest->filename)
        ->and($store->trashed())->toHaveCount(1)
        ->and($store->trashed()[0]->filename)->toBe($older->filename);
});

it('refuses the newest archive through the domain guard', function (): void {
    $store = new FakeBackupArchiveStore;
    $newest = $store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    expect(fn () => app(DeleteBackupsAction::class)->execute([$newest->uuid], null))
        ->toThrow(BackupDeletionRefused::class);

    expect($store->active())->toHaveCount(2);
});

it('rejects an unknown uuid before touching storage', function (): void {
    $store = new FakeBackupArchiveStore;
    $store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    expect(fn () => app(DeleteBackupsAction::class)->execute([(string) Str::uuid7()], null))
        ->toThrow(BackupNotFound::class);
});

it('validates the whole batch before moving any file', function (): void {
    $store = new FakeBackupArchiveStore;
    $newest = $store->seedActive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $older = $store->seedActive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    $this->app->instance(BackupArchiveStore::class, $store);
    $this->mock(AuditPort::class)->shouldNotReceive('log');

    expect(fn () => app(DeleteBackupsAction::class)->execute([$older->uuid, $newest->uuid], null))
        ->toThrow(BackupDeletionRefused::class);

    expect($store->active())->toHaveCount(2)
        ->and($store->trashed())->toHaveCount(0);
});
