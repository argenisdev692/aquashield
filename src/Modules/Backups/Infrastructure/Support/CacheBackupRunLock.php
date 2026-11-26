<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Modules\Backups\Application\Ports\BackupRunLock;

/**
 * Cache-backed implementation of the backup run lock.
 *
 * `backups:database-run` is deliberately not the job's `backups:database`
 * middleware key: a job that already holds the middleware lock must not fail
 * its own inner acquire.
 */
final class CacheBackupRunLock implements BackupRunLock
{
    private const string KEY = 'backups:database-run';

    private const int TTL_SECONDS = 1800;

    private ?Lock $lock = null;

    public function acquire(): bool
    {
        $this->lock = Cache::lock(self::KEY, self::TTL_SECONDS);

        if (! $this->lock->get()) {
            $this->lock = null;

            return false;
        }

        return true;
    }

    public function release(): void
    {
        $this->lock?->release();
        $this->lock = null;
    }
}
