<?php

declare(strict_types=1);

use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;

function inventoryArchive(string $filename, string $takenAt, bool $trashed = false): BackupArchive
{
    return new BackupArchive(
        uuid: 'uuid-'.$filename,
        disk: 'local',
        path: ($trashed ? '.backups-quarantine/backups/' : 'backups/').$filename,
        filename: $filename,
        sizeBytes: 1024,
        takenAt: new DateTimeImmutable($takenAt, new DateTimeZone('UTC')),
        trashed: $trashed,
    );
}

it('picks the newest archive by taken-at, not by insertion order', function (): void {
    $newest = inventoryArchive('2026-10-02-00-00-00.zip', '2026-10-02 00:00:00');
    $older = inventoryArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');

    expect(BackupInventory::fromList([$newest, $older])->newest()?->uuid)->toBe($newest->uuid)
        ->and(BackupInventory::fromList([$older, $newest])->newest()?->uuid)->toBe($newest->uuid);
});

it('answers an empty inventory with no newest and no match', function (): void {
    $inventory = BackupInventory::fromList([]);

    expect($inventory->newest())->toBeNull()
        ->and($inventory->find('anything'))->toBeNull();
});

it('resolves a selected uuid to its archive', function (): void {
    $archive = inventoryArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $inventory = BackupInventory::fromList([$archive]);

    expect($inventory->select([$archive->uuid]))->toBe([$archive])
        ->and($inventory->find($archive->uuid))->toBe($archive);
});

it('refuses the whole selection when one uuid is gone', function (): void {
    $archive = inventoryArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $inventory = BackupInventory::fromList([$archive]);

    expect(fn () => $inventory->select([$archive->uuid, 'uuid-gone']))
        ->toThrow(BackupNotFound::class);
});

it('matches a filename only on the same disk', function (): void {
    $archive = inventoryArchive('2026-10-01-00-00-00.zip', '2026-10-01 00:00:00');
    $inventory = BackupInventory::fromList([$archive]);

    expect($inventory->containsFilename('local', $archive->filename))->toBeTrue()
        ->and($inventory->containsFilename('s3', $archive->filename))->toBeFalse();
});
