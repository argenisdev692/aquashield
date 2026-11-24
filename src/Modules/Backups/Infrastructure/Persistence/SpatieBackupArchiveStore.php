<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\FileAttributes;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;
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
        return BackupInventory::fromList($this->active())->find($uuid);
    }

    public function findTrashed(string $uuid): ?BackupArchive
    {
        return BackupInventory::fromList($this->trashed())->find($uuid);
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
                array_push($rows, ...$this->quarantined($diskName));

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

    /**
     * One recursive listing call, not one metadata call per file: on S3 the
     * size and the last-modified timestamp arrive with the listing, so a
     * quarantine holding fifty archives costs one round trip instead of fifty.
     *
     * @return list<BackupArchive>
     */
    private function quarantined(string $diskName): array
    {
        $rows = [];

        foreach ($this->filesystems->disk($diskName)->listContents($this->quarantinePrefix(), true) as $item) {
            if (! $item instanceof FileAttributes) {
                continue;
            }

            $path = $item->path();

            if (! str_ends_with(strtolower($path), '.zip')) {
                continue;
            }

            $rows[] = new BackupArchive(
                uuid: $this->uuid('quarantine', $diskName, $path),
                disk: $diskName,
                path: $path,
                filename: basename($path),
                sizeBytes: (int) ($item->fileSize() ?? 0),
                takenAt: $this->takenAt($path, $item->lastModified()),
                trashed: true,
            );
        }

        return $rows;
    }

    /**
     * The dump filename is the authoritative date. When it does not parse, the
     * listing timestamp is the fallback — and when that is missing too the
     * archive is dated "now", never 1970: an unreadable timestamp must not make
     * a file look old enough for `backups:purge-quarantine` to destroy it.
     */
    private function takenAt(string $path, ?int $lastModified): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            BackupJob::FILENAME_FORMAT,
            basename($path),
            new DateTimeZone('UTC'),
        );

        if ($date !== false) {
            return $date;
        }

        return $lastModified === null
            ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : new DateTimeImmutable('@'.$lastModified);
    }

    /**
     * The public archive id.
     *
     * It has to be stable: the inventory is a filesystem, so there is no row to
     * store a random UUIDv7 in, and a v7 regenerated per request would make
     * every `GET /backups/{uuid}` link expire on the next page load. It also has
     * to be unguessable: the dump filename is a predictable timestamp, so a
     * plain `uuid5(state|disk|path)` would let an actor holding only
     * `VIEW_BACKUPS` (no list permission) enumerate the inventory by computing
     * ids. Keying the name with the application key keeps both properties —
     * stable across requests, not derivable without the secret.
     *
     * Consequence: rotating `APP_KEY` rotates every archive id. That is the
     * same operation that already invalidates sessions and encrypted payloads,
     * and no id is stored anywhere, so nothing needs migrating.
     */
    private function uuid(string $state, string $disk, string $path): string
    {
        $name = hash_hmac('sha256', $state.'|'.$disk.'|'.$path, (string) config('app.key'));

        return Uuid::uuid5(Uuid::NAMESPACE_URL, $name)->toString();
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
