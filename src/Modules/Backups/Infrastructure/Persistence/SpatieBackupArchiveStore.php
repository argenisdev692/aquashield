<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\FileAttributes;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupDestinationUnavailable;
use Modules\Backups\Domain\Exceptions\BackupMoveInProgress;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Models\BackupArchive;
use Modules\Backups\Domain\Models\BackupInventory;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Throwable;

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
    /*
     * v2 caches scalar rows, not `BackupArchive` objects. The v1 keys held
     * objects, and `config/cache.php` sets `serializable_classes => false`
     * (Laravel 13's default), so a cache hit unserialized them as
     * `__PHP_Incomplete_Class`. The version bump abandons any warm v1 entry;
     * it expires on its own within the TTL. The environment segment keeps a
     * staging and a production app that share one Redis from reading each
     * other's inventory.
     */
    private const string CACHE_PREFIX = 'backups.inventory';

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
        return $this->hydrated($this->cacheKey('active'), trashed: false);
    }

    public function trashed(): array
    {
        return $this->hydrated($this->cacheKey('trashed'), trashed: true);
    }

    public function findActive(string $uuid): ?BackupArchive
    {
        return BackupInventory::fromList($this->active())->find($uuid);
    }

    public function disks(): array
    {
        return $this->configuredDisks();
    }

    /**
     * Read the inventory through the cache, degrading to a direct listing when
     * the cache backend is unavailable.
     *
     * The cached value is a scalar payload (see `payload()`), never domain
     * objects: the configured store unserializes with `allowed_classes => false`
     * (`config/cache.php`), so caching `BackupArchive` instances would come back
     * as `__PHP_Incomplete_Class` on a cache hit.
     *
     * @return list<BackupArchive>
     */
    private function hydrated(string $cacheKey, bool $trashed): array
    {
        $rows = $this->cachedPayload($cacheKey, $trashed);

        if (! is_array($rows)) {
            return $this->collect(trashed: $trashed);
        }

        $archives = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                return $this->collect(trashed: $trashed);
            }

            $archive = $this->hydrate($row);

            // A payload this class did not write is a cache miss, not a reason
            // to render a broken inventory.
            if ($archive === null) {
                return $this->collect(trashed: $trashed);
            }

            $archives[] = $archive;
        }

        return $archives;
    }

    /**
     * A value read from an untyped cache store — `mixed` is the honest
     * contract, because a warm entry is whatever the backend stored, not
     * whatever this class hoped to read.
     */
    private function cachedPayload(string $cacheKey, bool $trashed): mixed
    {
        return rescue(
            fn (): array => Cache::remember(
                $cacheKey,
                self::CACHE_TTL_SECONDS,
                fn (): array => $this->payload($trashed),
            ),
            fn (): array => $this->payload($trashed),
            report: false,
        );
    }

    /**
     * The cacheable shape: scalars only, so the value survives a store that
     * refuses to unserialize classes.
     *
     * @return list<array{uuid: string, disk: string, path: string, filename: string, size_bytes: int, taken_at: string, trashed: bool}>
     */
    private function payload(bool $trashed): array
    {
        return array_map(
            static fn (BackupArchive $archive): array => [
                'uuid' => $archive->uuid,
                'disk' => $archive->disk,
                'path' => $archive->path,
                'filename' => $archive->filename,
                'size_bytes' => $archive->sizeBytes,
                'taken_at' => $archive->takenAt->format(DATE_ATOM),
                'trashed' => $archive->trashed,
            ],
            $this->collect(trashed: $trashed),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?BackupArchive
    {
        foreach (['uuid', 'disk', 'path', 'filename', 'size_bytes', 'taken_at', 'trashed'] as $key) {
            if (! array_key_exists($key, $row)) {
                return null;
            }
        }

        if (! is_string($row['uuid'])
            || ! is_string($row['disk'])
            || ! is_string($row['path'])
            || ! is_string($row['filename'])
            || ! is_int($row['size_bytes'])
            || ! is_string($row['taken_at'])
            || ! is_bool($row['trashed'])) {
            return null;
        }

        try {
            $takenAt = new DateTimeImmutable($row['taken_at']);
        } catch (Throwable) {
            return null;
        }

        return new BackupArchive(
            uuid: $row['uuid'],
            disk: $row['disk'],
            path: $row['path'],
            filename: $row['filename'],
            sizeBytes: $row['size_bytes'],
            takenAt: $takenAt,
            trashed: $row['trashed'],
        );
    }

    public function quarantine(BackupArchive $archive): void
    {
        $this->withMoveLock($archive, function () use ($archive): void {
            $disk = $this->filesystems->disk($archive->disk);
            $target = $this->quarantinePath($archive->filename);

            if (! $disk->exists($archive->path)) {
                throw BackupNotFound::gone();
            }

            if ($disk->exists($target)) {
                throw new RuntimeException("The quarantine already contains {$archive->filename}.");
            }

            if (! $disk->move($archive->path, $target)) {
                throw new RuntimeException("Could not quarantine {$archive->filename}.");
            }

            $this->forgetInventory();
        });
    }

    public function restore(BackupArchive $archive): void
    {
        $this->withMoveLock($archive, function () use ($archive): void {
            $disk = $this->filesystems->disk($archive->disk);
            $target = $this->backupName().'/'.$archive->filename;

            if (! $disk->exists($archive->path)) {
                throw BackupNotFound::gone();
            }

            // The guard already checked the inventory; this is the last line of
            // defence against a concurrent restore of the same filename.
            if ($disk->exists($target)) {
                throw new RuntimeException("An active archive already exists at {$target}.");
            }

            if (! $disk->move($archive->path, $target)) {
                throw new RuntimeException("Could not restore {$archive->filename}.");
            }

            $this->forgetInventory();
        });
    }

    public function purge(BackupArchive $archive): void
    {
        $this->withMoveLock($archive, function () use ($archive): void {
            if (! $this->filesystems->disk($archive->disk)->delete($archive->path)) {
                throw new RuntimeException("Could not purge {$archive->filename}.");
            }

            $this->forgetInventory();
        });
    }

    /**
     * Serialise mutations of one archive. The existence checks are
     * check-then-act, and Flysystem's `move` is copy+delete on object stores
     * (not atomic), so two concurrent requests could both pass the checks.
     */
    private function withMoveLock(BackupArchive $archive, callable $operation): void
    {
        $lock = Cache::lock(
            'backups:move:'.$archive->disk.':'.$archive->filename,
            300,
        );

        if (! $lock->get()) {
            throw BackupMoveInProgress::filename($archive->filename);
        }

        try {
            $operation();
        } finally {
            $lock->release();
        }
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
        $listed = 0;
        $failures = 0;

        foreach ($this->configuredDisks() as $diskName) {
            if ($trashed) {
                try {
                    array_push($rows, ...$this->quarantined($diskName));
                    $listed++;
                } catch (Throwable $exception) {
                    $failures++;
                    logger()->warning('Backup quarantine is unreachable.', [
                        'disk' => $diskName,
                        'exception' => $exception::class,
                    ]);
                }

                continue;
            }

            try {
                $destination = BackupDestination::create($diskName, $this->backupName());

                if ($destination->connectionError() !== null) {
                    $failures++;
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

                $listed++;
            } catch (Throwable $exception) {
                $failures++;
                logger()->warning('Backup destination is unreachable.', [
                    'disk' => $diskName,
                    'exception' => $exception::class,
                ]);
            }
        }

        // An empty inventory and an unreachable disk are different facts.
        if ($listed === 0 && $failures > 0) {
            throw BackupDestinationUnavailable::allDisks();
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
        $timezone = new DateTimeZone((string) config('app.timezone', 'UTC'));

        $date = DateTimeImmutable::createFromFormat(
            BackupJob::FILENAME_FORMAT,
            basename($path),
            $timezone,
        );

        if ($date !== false) {
            return $date;
        }

        return $lastModified === null
            ? new DateTimeImmutable('now', $timezone)
            : (new DateTimeImmutable('@'.$lastModified))->setTimezone($timezone);
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
        // Reads already degrade to a direct listing when the cache store is
        // down; a failed invalidation must not turn a successful file move
        // into a 500 for the caller.
        rescue(function (): void {
            Cache::forget($this->cacheKey('active'));
            Cache::forget($this->cacheKey('trashed'));
        }, report: false);
    }

    private function cacheKey(string $state): string
    {
        return self::CACHE_PREFIX.'.'.app()->environment().'.'.$state.'.v2';
    }
}
