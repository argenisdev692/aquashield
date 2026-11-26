<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

/**
 * No configured destination could be listed.
 *
 * An empty inventory and an unreachable disk are not the same fact: the panel
 * must not tell an operator there are no backups when the truth is that it
 * cannot see them.
 */
final class BackupDestinationUnavailable extends RuntimeException
{
    public static function allDisks(): self
    {
        return new self('The backup destination is unreachable; the inventory cannot be read right now.');
    }
}
