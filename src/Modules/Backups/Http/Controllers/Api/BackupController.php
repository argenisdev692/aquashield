<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers\Api;

use Illuminate\Http\Request;
use Modules\Backups\Application\Ports\BackupActivityLog;
use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Data\BackupDetailData;
use Modules\Backups\Data\BackupFilterData;
use Modules\Backups\Data\BackupListData;

/**
 * Read-only REST surface for external and mobile clients.
 *
 * It reuses the web query and the same `BackupArchiveData` rows, so the two
 * transports cannot drift. Destructive verbs — and the archive download — stay
 * on the session + CSRF web routes on purpose: a stolen bearer token must not
 * be able to quarantine the only recovery point, nor to walk away with a full
 * copy of the database.
 */
final readonly class BackupController
{
    public function __construct(
        private BackupIndexQuery $backups,
        private BackupActivityLog $activity,
    ) {}

    public function index(Request $request): BackupListData
    {
        $page = $this->backups->execute(BackupFilterData::fromRequest($request));

        return new BackupListData(
            rows: $page->items,
            total: $page->total,
            page: $page->page,
            perPage: $page->perPage,
            lastPage: $page->lastPage,
        );
    }

    public function show(string $backup): BackupDetailData
    {
        $archive = $this->backups->find($backup);

        if ($archive === null) {
            abort(404);
        }

        return new BackupDetailData(
            backup: $archive,
            activities: $this->activity->forFilename($archive->filename),
        );
    }
}
