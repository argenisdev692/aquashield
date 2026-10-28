<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Models;

use DateTimeImmutable;

/**
 * One database dump archive, active or quarantined.
 *
 * The identity is the disk plus the storage path: a filename alone is not
 * unique across destinations. The UUID is derived by the adapter from that
 * pair, so the domain never needs a framework UUID factory.
 */
final readonly class BackupArchive
{
    public function __construct(
        public string $uuid,
        public string $disk,
        public string $path,
        public string $filename,
        public int $sizeBytes,
        public DateTimeImmutable $takenAt,
        public bool $trashed = false,
    ) {}

    public function ageInDays(DateTimeImmutable $now): int
    {
        return max(0, (int) floor(($now->getTimestamp() - $this->takenAt->getTimestamp()) / 86400));
    }

    public function isSameArchive(self $other): bool
    {
        return $this->disk === $other->disk && $this->path === $other->path;
    }
}
