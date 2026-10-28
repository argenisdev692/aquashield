<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

final class BackupRunFailed extends RuntimeException
{
    public static function exitCode(int $exitCode): self
    {
        return new self(
            "backup:run exited with code {$exitCode}. "
            .'The package log in storage/logs/laravel.log has the details.',
        );
    }
}
