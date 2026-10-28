<?php

declare(strict_types=1);

namespace Modules\Backups\Data;

use Modules\Backups\Domain\Models\BackupArchive;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The response shape for one archive. Explicit allowlist: storage paths stay
 * inside the module, only the disk name and the filename cross the boundary.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BackupArchiveData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $disk,
        public readonly string $filename,
        public readonly int $sizeBytes,
        public readonly string $sizeHuman,
        public readonly string $takenAt,
        public readonly int $ageDays,
        public readonly bool $isNewest,
        public readonly bool $isTrashed,
    ) {}

    public static function fromArchive(BackupArchive $archive, bool $isNewest, \DateTimeImmutable $now): self
    {
        return new self(
            uuid: $archive->uuid,
            disk: $archive->disk,
            filename: $archive->filename,
            sizeBytes: $archive->sizeBytes,
            sizeHuman: self::humanSize($archive->sizeBytes),
            takenAt: $archive->takenAt->format('Y-m-d H:i'),
            ageDays: $archive->ageInDays($now),
            isNewest: $isNewest,
            isTrashed: $archive->trashed,
        );
    }

    private static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $value, $units[$unit]);
    }
}
