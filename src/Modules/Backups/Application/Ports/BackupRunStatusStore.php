<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Ports;

use Modules\Backups\Data\BackupRunStatusData;
use Modules\Backups\Domain\Enums\BackupRunStatus;

interface BackupRunStatusStore
{
    public function current(): ?BackupRunStatusData;

    public function markQueued(): BackupRunStatusData;

    public function update(BackupRunStatus $status): void;
}
