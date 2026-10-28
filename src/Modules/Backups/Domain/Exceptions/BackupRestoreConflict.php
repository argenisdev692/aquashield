<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

final class BackupRestoreConflict extends RuntimeException
{
    public static function filenameExists(string $filename): self
    {
        return new self(
            "Cannot restore {$filename}: an active archive with the same name already exists. "
            .'Refresh the list before retrying.',
        );
    }
}
