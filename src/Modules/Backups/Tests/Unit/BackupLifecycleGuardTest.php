<?php

declare(strict_types=1);

use Modules\Backups\Domain\Exceptions\BackupDeletionRefused;
use Modules\Backups\Domain\Exceptions\BackupRestoreConflict;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;
use Modules\Backups\Domain\Services\BackupLifecycleGuard;

function guardArchive(string $filename, string $takenAt, bool $trashed = false): BackupArchive
{
    return new BackupArchive(
        uuid: $filename,
        disk: 'local',
        path: ($trashed ? '.backups-quarantine/backups/' : 'backups/').$filename,
        filename: $filename,
        sizeBytes: 1024,
        takenAt: new DateTimeImmutable($takenAt, new DateTimeZone('UTC')),
        trashed: $trashed,
    );
}

it('refuses to delete the newest active archive', function (): void {
    $guard = new BackupLifecycleGuard;
    $older = guardArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $newest = guardArchive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    expect(fn () => $guard->assertDeletable($newest, BackupInventory::fromList([$newest, $older])))
        ->toThrow(BackupDeletionRefused::class);
});

it('allows deleting an older archive', function (): void {
    $guard = new BackupLifecycleGuard;
    $older = guardArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $newest = guardArchive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');

    $guard->assertDeletable($older, BackupInventory::fromList([$newest, $older]));

    expect(true)->toBeTrue();
});

it('refuses the only archive because it is also the newest', function (): void {
    $guard = new BackupLifecycleGuard;
    $only = guardArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    expect(fn () => $guard->assertDeletable($only, BackupInventory::fromList([$only])))
        ->toThrow(BackupDeletionRefused::class);
});

it('refuses a restore that would overwrite an active filename', function (): void {
    $guard = new BackupLifecycleGuard;
    $active = guardArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $quarantined = guardArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00', trashed: true);

    expect(fn () => $guard->assertRestorable($quarantined, BackupInventory::fromList([$active])))
        ->toThrow(BackupRestoreConflict::class);
});

it('allows a restore when the destination filename is free', function (): void {
    $guard = new BackupLifecycleGuard;
    $active = guardArchive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $quarantined = guardArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00', trashed: true);

    $guard->assertRestorable($quarantined, BackupInventory::fromList([$active]));

    expect(true)->toBeTrue();
});
