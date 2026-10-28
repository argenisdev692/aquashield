<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Repositories;

use Modules\Backups\Data\BackupActivityData;

/**
 * Read-only view over the audit trail this module writes.
 *
 * There is deliberately no delete, restore or export: an audit trail a panel
 * can rewrite is not an audit trail (OWASP A09). Retention belongs to
 * `activitylog:clean`.
 */
interface BackupActivityLog
{
    /**
     * @return list<BackupActivityData>
     */
    public function recent(int $limit = 50): array;

    /**
     * The audit entries recorded for one archive filename.
     *
     * @return list<BackupActivityData>
     */
    public function forFilename(string $filename, int $limit = 20): array;
}
