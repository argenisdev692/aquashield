<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Enums;

enum BackupRunStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
