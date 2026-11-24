<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Exceptions;

use RuntimeException;

/**
 * A selected archive is no longer in the inventory: it was purged, or the list
 * the operator is looking at is stale.
 *
 * The message never echoes the UUID. The identifier is guessable to anyone who
 * knows the filename format (see ARCHITECTURE.md), so the refusal is generic and
 * the client refreshes instead of learning which guess landed.
 */
final class BackupNotFound extends RuntimeException
{
    public static function gone(): self
    {
        return new self('One or more backups no longer exist. Refresh the list and try again.');
    }
}
