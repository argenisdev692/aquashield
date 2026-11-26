<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

/**
 * Another request is already moving this archive.
 *
 * The existence checks are check-then-act and Flysystem's move is copy+delete
 * on object stores, so the adapter serialises moves with a cache lock.
 */
final class BackupMoveInProgress extends RuntimeException
{
    public static function filename(string $filename): self
    {
        return new self("The archive {$filename} is already being moved. Refresh the list and try again.");
    }
}
