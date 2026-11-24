<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Ports;

use Modules\Backups\Data\BackupActivityData;

/**
 * Read-only view of the backup audit trail.
 *
 * `MAX_LIMIT` is the single declaration of the cap. The HTTP boundary validates
 * against it and the adapter clamps to it, so a new adapter cannot reopen an
 * unbounded query and the controller cannot accept a number the query will not
 * honour.
 */
interface BackupActivityLog
{
    public const int MAX_LIMIT = 200;

    /**
     * @return list<BackupActivityData>
     */
    public function recent(int $limit = 50): array;

    /**
     * @return list<BackupActivityData>
     */
    public function forFilename(string $filename, int $limit = 20): array;
}
