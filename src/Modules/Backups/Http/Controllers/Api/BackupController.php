<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers\Api;

use Illuminate\Http\Request;
use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Application\Repositories\BackupActivityLog;
use Modules\Backups\Data\BackupDetailData;
use Modules\Backups\Data\BackupFilterData;
use Modules\Backups\Data\BackupListData;

/**
 * Read-only REST surface. Scramble documents it because the routes live under
 * `api/`; destructive operations stay on the session + CSRF web routes, where
 * a stolen bearer token cannot quarantine the only recovery point.
 */
final readonly class BackupController
{
    public function __construct(
        private BackupIndexQuery $backups,
        private BackupActivityLog $activity,
    ) {}

    public function index(Request $request): BackupListData
    {
        $filters = BackupFilterData::fromRequest($request);
        $page = $this->backups->execute($filters);

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
