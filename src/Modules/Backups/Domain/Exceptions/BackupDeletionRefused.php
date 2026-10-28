<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

/**
 * The newest completed archive is never deleted from the panel.
 *
 * Without this rule a request could leave the application with no recent
 * recovery point until the next scheduled dump. Irreversible retention stays
 * with `backup:clean`, which the package already guarantees never deletes the
 * newest archive.
 */
final class BackupDeletionRefused extends RuntimeException
{
    public static function newest(string $filename): self
    {
        return new self(
            "The newest database backup ({$filename}) cannot be removed. "
            .'Wait for the next successful dump, or let the retention policy rotate it.',
        );
    }
}
