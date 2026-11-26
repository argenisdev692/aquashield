<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
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

    // The filter DTO is built inside the method, so Scramble cannot infer the
    // query contract from a type-hint. Declare it here; the names and rules
    // mirror `BackupFilterData`.
    #[QueryParameter('search', description: 'Free-text match on the archive filename or disk.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Inventory to list: active, trashed or all.', type: 'string', default: 'active')]
    #[QueryParameter('filter[disk]', description: 'Restrict to one configured destination disk.', type: 'string')]
    #[QueryParameter('filter[date_from]', description: 'Inclusive start of the taken-at window, Y-m-d.', type: 'string', format: 'date')]
    #[QueryParameter('filter[date_to]', description: 'Inclusive end of the taken-at window, Y-m-d.', type: 'string', format: 'date')]
    #[QueryParameter('sort', description: 'Sort field, prefix with - for descending: taken_at, filename, size_bytes or disk.', type: 'string', default: '-taken_at')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', default: 1)]
    #[QueryParameter('per_page', description: 'Rows per page (1-100).', type: 'integer', default: 15)]
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

    #[PathParameter('backup', description: 'Archive UUID.', type: 'string', format: 'uuid')]
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
