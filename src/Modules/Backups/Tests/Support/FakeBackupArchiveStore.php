<?php

declare(strict_types=1);

namespace Modules\Backups\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Domain\Models\BackupArchive;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * In-memory archive store used by the HTTP and handler tests.
 *
 * It mirrors the real adapter's state transition: an archive gets a different
 * UUID in quarantine, so a test cannot accidentally pass by treating the
 * active and trashed rows as the same identifier.
 */
final class FakeBackupArchiveStore implements BackupArchiveStore
{
    /** @var array<string, BackupArchive> */
    private array $active = [];

    /** @var array<string, BackupArchive> */
    private array $trashed = [];

    /** @var list<string> */
    public array $purged = [];

    public string $content = 'PK fake zip payload';

    public function seedActive(
        string $filename,
        string $takenAt = '2026-10-01 00:00:00',
        int $size = 1024,
        string $disk = 'local',
    ): BackupArchive {
        $archive = $this->make('active', $filename, $takenAt, $size, $disk);
        $this->active[$archive->uuid] = $archive;

        return $archive;
    }

    public function seedTrashed(
        string $filename,
        string $takenAt = '2026-10-01 00:00:00',
        int $size = 1024,
        string $disk = 'local',
    ): BackupArchive {
        $archive = $this->make('quarantine', $filename, $takenAt, $size, $disk);
        $this->trashed[$archive->uuid] = $archive;

        return $archive;
    }

    public function active(): array
    {
        return array_values($this->active);
    }

    public function trashed(): array
    {
        return array_values($this->trashed);
    }

    public function findActive(string $uuid): ?BackupArchive
    {
        return $this->active[$uuid] ?? null;
    }

    public function findTrashed(string $uuid): ?BackupArchive
    {
        return $this->trashed[$uuid] ?? null;
    }

    public function disks(): array
    {
        return ['local'];
    }

    public function quarantine(BackupArchive $archive): void
    {
        if (! isset($this->active[$archive->uuid])) {
            throw new RuntimeException('The archive is not active.');
        }

        unset($this->active[$archive->uuid]);

        $trashed = $this->make(
            'quarantine',
            $archive->filename,
            $archive->takenAt->format('Y-m-d H:i:s'),
            $archive->sizeBytes,
            $archive->disk,
        );

        $this->trashed[$trashed->uuid] = $trashed;
    }

    public function restore(BackupArchive $archive): void
    {
        if (! isset($this->trashed[$archive->uuid])) {
            throw new RuntimeException('The archive is not quarantined.');
        }

        unset($this->trashed[$archive->uuid]);

        $active = $this->make(
            'active',
            $archive->filename,
            $archive->takenAt->format('Y-m-d H:i:s'),
            $archive->sizeBytes,
            $archive->disk,
        );

        $this->active[$active->uuid] = $active;
    }

    public function purge(BackupArchive $archive): void
    {
        unset($this->trashed[$archive->uuid]);
        $this->purged[] = $archive->filename;
    }

    public function stream(BackupArchive $archive): mixed
    {
        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Could not open the in-memory stream.');
        }

        fwrite($stream, $this->content);
        rewind($stream);

        return $stream;
    }

    private function make(
        string $state,
        string $filename,
        string $takenAt,
        int $size,
        string $disk,
    ): BackupArchive {
        $trashed = $state === 'quarantine';

        return new BackupArchive(
            uuid: Uuid::uuid5(Uuid::NAMESPACE_URL, "fake-{$state}|{$disk}|{$filename}")->toString(),
            disk: $disk,
            path: ($trashed ? '.backups-quarantine/backups/' : 'backups/').$filename,
            filename: $filename,
            sizeBytes: $size,
            takenAt: new DateTimeImmutable($takenAt, new DateTimeZone('UTC')),
            trashed: $trashed,
        );
    }
}
