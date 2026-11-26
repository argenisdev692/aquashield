<?php

declare(strict_types=1);

namespace Shared\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A storage write did not reach the provider.
 *
 * Distinct from a read miss: callers must not persist a key that was never
 * written. The R2 adapter throws this instead of returning a key on a failed
 * `put()`, so a profile/asset row cannot point at a missing object.
 */
final class StorageWriteException extends RuntimeException
{
    public static function forPath(string $path, ?Throwable $previous = null): self
    {
        return new self("Storage object [{$path}] could not be written.", previous: $previous);
    }
}
