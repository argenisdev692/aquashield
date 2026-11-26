<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Ports;

/**
 * Cross-entry mutual exclusion for a database dump.
 *
 * The scheduled `backups:run` and the panel's queued job both acquire this
 * lock. spatie/laravel-backup uses one fixed temporary directory and deletes
 * it on start, so two concurrent runs would destroy each other's work. The
 * job's `ShouldBeUnique`/`WithoutOverlapping` locks are job-scoped and do not
 * cover the scheduler path.
 */
interface BackupRunLock
{
    /**
     * @return bool true when this caller owns the lock, false when another run holds it
     */
    public function acquire(): bool;

    public function release(): void;
}
