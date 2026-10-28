<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Repositories;

use Modules\Backups\Domain\Exceptions\BackupRunFailed;

interface DatabaseBackupRunner
{
    /**
     * Dump the configured database connections only — never the filesystem.
     *
     * @throws BackupRunFailed
     */
    public function run(): void;
}
