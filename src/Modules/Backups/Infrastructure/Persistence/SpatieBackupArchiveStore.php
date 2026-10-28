<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Domain\Models\BackupArchive;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Backup\BackupJob;

/**
 * Adapter over spatie/laravel-backup's destination listing plus a sibling
 * quarantine directory used as the reversible delete.
 *
 * The package owns archive creation, naming and cleanup. This class owns the
 * translation to `BackupArchive` and the move semantics; it adds no retention
 * rule of its own.
 */
final readonly class SpatieBackupArchiveStore implements BackupArchiveStore
{
    private const string ACTIVE_CACHE_KEY = 'backups.inventory.active.v1';

    private const string TRASHED_CACHE_KEY = 'backups.inventory.trashed.v1';

    /**
     * S3 listing is a network round trip per destination. Thirty seconds is
     * short enough that a dump finishing in another process appears quickly,
     * and long enough to stop a polled index from hammering the disk.
     */
    private const int CACHE_TTL_SECONDS = 30;

    public function __construct(
        private FilesystemFactory $filesystems,
    ) {}

    public function active(): array
    {
        /** @var list<BackupArchive> $rows */
        $rows = Cache::remember(
            self::ACTIVE_CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->collect(trashed: false),
        );

        return $rows;
    }

    public function trashed(): array
    {
        /** @var list<BackupArchive> $rows */
        $rows = Cache::remember(
            self::TRASHED_CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->collect(trashed: true),
        );

        return $rows;
    }

    public function findActive(string $uuid): ?BackupArchive
    {
        return $this->find($this->active(), $uuid);
    }

    public function findTrashed(string $uuid): ?BackupArchive
    {
        return $this->find($this->trashed(), $uuid);
    }

    public function disks(): array
    {
        return $this->configuredDisks();
    }

    public function quarantine(BackupArchive $archive): void
    {
        $disk = $this->filesystems->disk($archive->disk);
        $target = $this->quarantinePath($archive->filename);

        if ($disk->exists($target)) {
            throw new RuntimeException("The quarantine already contains {$archive->filename}.");
        }

        if (! $disk->move($archive->path, $target)) {
            throw new RuntimeException("Could not quarantine {$archive->filename}.");
        }

        $this->forgetInventory();
    }

    public function restore(BackupArchive $archive): void
    {
        $disk = $this->filesystems->disk($archive->disk);
        $target = $this->backupName().'/'.$archive->filename;

        // The guard already checked the inventory; this is the last line of
        // defence against a concurrent restore of the same filename.
        if ($disk->exists($target)) {
            throw new RuntimeException("An active archive already exists at {$target}.");
        }

        if (! $disk->move($archive->path, $target)) {
            throw new RuntimeException("Could not restore {$archive->filename}.");
        }

        $this->forgetInventory();
    }

    public function purge(BackupArchive $archive): void
    {
        if (! $this->filesystems->disk($archive->disk)->delete($archive->path)) {
            throw new RuntimeException("Could not purge {$archive->filename}.");
        }

        $this->forgetInventory();
    }

    public function stream(BackupArchive $archive): mixed
    {
        $stream = $this->filesystems->disk($archive->disk)->readStream($archive->path);

        if ($stream === null) {
            throw new RuntimeException("Could not read {$archive->filename}.");
        }

        return $stream;
    }

    /**
     * @return list<BackupArchive>
     */
    private function collect(bool $trashed): array
    {
        $rows = [];

        foreach ($this->configuredDisks() as $diskName) {
            if ($trashed) {
                foreach ($this->filesystems->disk($diskName)->allFiles($this->quarantinePrefix()) as $path) {
                    if (str_ends_with(strtolower($path), '.zip')) {
                        $rows[] = $this->make($diskName, $path, trashed: true);
                    }
                }

                continue;
            }

            $destination = BackupDestination::create($diskName, $this->backupName());

            if ($destination->connectionError() !== null) {
                logger()->warning('Backup destination is unreachable.', ['disk' => $diskName]);

                continue;
            }

            foreach ($destination->backups() as $backup) {
                $rows[] = new BackupArchive(
                    uuid: $this->uuid('active', $diskName, $backup->path()),
                    disk: $diskName,
                    path: $backup->path(),
                    filename: basename($backup->path()),
                    sizeBytes: (int) $backup->sizeInBytes(),
                    takenAt: DateTimeImmutable::createFromInterface($backup->date()),
                );
            }
        }

        return $rows;
    }

    private function make(string $diskName, string $path, bool $trashed): BackupArchive
    {
        $disk = $this->filesystems->disk($diskName);

        return new BackupArchive(
            uuid: $this->uuid($trashed ? 'quarantine' : 'active', $diskName, $path),
            disk: $diskName,
            path: $path,
            filename: basename($path),
            sizeBytes: (int) $disk->size($path),
            takenAt: $this->takenAt($disk, $path),
            trashed: $trashed,
        );
    }

    private function takenAt(Filesystem $disk, string $path): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            BackupJob::FILENAME_FORMAT,
            basename($path),
            new DateTimeZone('UTC'),
        );

        if ($date !== false) {
            return $date;
        }

        return new DateTimeImmutable('@'.$disk->lastModified($path));
    }

    private function uuid(string $state, string $disk, string $path): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $state.'|'.$disk.'|'.$path)->toString();
    }

    /**
     * @param list<BackupArchive> $rows
     */
    private function find(array $rows, string $uuid): ?BackupArchive
    {
        foreach ($rows as $row) {
            if ($row->uuid === $uuid) {
                return $row;
            }
        }

        return null;
    }

    private function backupName(): string
    {
        return (string) config('backup.backup.name', 'laravel-backup');
    }

    /**
     * @return list<string>
     */
    private function configuredDisks(): array
    {
        $disks = config('backup.backup.destination.disks', []);

        return is_array($disks) ? array_values(array_map(strval(...), $disks)) : [];
    }

    private function quarantinePrefix(): string
    {
        $directory = trim((string) config('backup.quarantine.directory', '.backups-quarantine'), '/');

        return $directory.'/'.$this->backupName();
    }

    private function quarantinePath(string $filename): string
    {
        return $this->quarantinePrefix().'/'.$filename;
    }

    private function forgetInventory(): void
    {
        Cache::forget(self::ACTIVE_CACHE_KEY);
        Cache::forget(self::TRASHED_CACHE_KEY);
    }
}
