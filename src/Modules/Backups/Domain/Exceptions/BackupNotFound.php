<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

final class BackupNotFound extends RuntimeException
{
    public static function forUuid(string $uuid): self
    {
        return new self('One or more backups no longer exist. Refresh the list and try again.');
    }
}
