<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;
use Modules\Backups\Application\Actions\DeleteBackupsAction;
use Modules\Backups\Application\Actions\RestoreBackupsAction;
use Modules\Backups\Application\Actions\RunDatabaseBackupAction;
use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Application\Repositories\BackupActivityLog;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Data\BackupFilterData;
use Modules\Backups\Domain\Exceptions\BackupDeletionRefused;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Exceptions\BackupRestoreConflict;
use Modules\Backups\Http\Requests\BulkDeleteBackupsRequest;
use Modules\Backups\Http\Requests\BulkRestoreBackupsRequest;
use Shared\Application\DTOs\BulkUuidsData;

final readonly class BackupController
{
    public function __construct(
        private BackupIndexQuery $backups,
        private BackupArchiveStore $archives,
        private BackupActivityLog $activity,
        private DeleteBackupsAction $deleteBackups,
        private RestoreBackupsAction $restoreBackups,
        private RunDatabaseBackupAction $runBackup,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = BackupFilterData::fromRequest($request);
        $page = $this->backups->execute($filters);
        $actor = $request->user();
        $can = $this->abilities($actor);

        if ($this->wantsJson($request)) {
            return response()->json([
                'rows' => $page->items,
                'total' => $page->total,
                'page' => $page->page,
                'per_page' => $page->perPage,
                'last_page' => $page->lastPage,
                'filters' => $filters,
                'can' => $can,
            ]);
        }

        return inertia('Backups/Index', [
            'rows' => $page->items,
            'total' => $page->total,
            'currentPage' => $page->page,
            'perPage' => $page->perPage,
            'filters' => $filters,
            'disks' => $this->archives->disks(),
            'can' => $can,
        ]);
    }

    public function show(Request $request, string $backup): JsonResponse|InertiaResponse
    {
        $archive = $this->backups->find($backup);

        if ($archive === null) {
            abort(404);
        }

        $activities = $this->activity->forFilename($archive->filename);
        $actor = $request->user();

        if ($this->wantsJson($request)) {
            return response()->json([
                'backup' => $archive,
                'activities' => $activities,
            ]);
        }

        return inertia('Backups/Show', [
            'backup' => $archive,
            'activities' => $activities,
            'can' => [
                'download' => $actor instanceof User && $actor->can('DOWNLOAD_BACKUPS'),
                'delete' => $actor instanceof User
                    && $actor->can('DELETE_BACKUPS')
                    && ! $archive->isTrashed
                    && ! $archive->isNewest,
                'restore' => $actor instanceof User
                    && $actor->can('RESTORE_BACKUPS')
                    && $archive->isTrashed,
            ],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $this->runBackup->execute($request->user());

        return response()->json(['status' => 'queued'], 202);
    }

    public function destroy(Request $request, string $backup): Response
    {
        $this->assertDomainOutcome(
            fn () => $this->deleteBackups->execute([$backup], $request->user()),
        );

        return response()->noContent();
    }

    public function restore(Request $request, string $backup): Response
    {
        $this->assertDomainOutcome(
            fn () => $this->restoreBackups->execute([$backup], $request->user()),
        );

        return response()->noContent();
    }

    public function bulkDelete(BulkDeleteBackupsRequest $request): Response
    {
        $uuids = BulkUuidsData::from($request->validated())->uuids;

        $this->assertDomainOutcome(
            fn () => $this->deleteBackups->execute($uuids, $request->user()),
        );

        return response()->noContent();
    }

    public function bulkRestore(BulkRestoreBackupsRequest $request): Response
    {
        $uuids = BulkUuidsData::from($request->validated())->uuids;

        $this->assertDomainOutcome(
            fn () => $this->restoreBackups->execute($uuids, $request->user()),
        );

        return response()->noContent();
    }

    /**
     * The same capability map the page receives. Hidden UI is a courtesy; the
     * route middleware is the control.
     *
     * @return array<string, bool>
     */
    private function abilities(?User $actor): array
    {
        return [
            'create' => $actor instanceof User && $actor->can('CREATE_BACKUPS'),
            'delete' => $actor instanceof User && $actor->can('DELETE_BACKUPS'),
            'bulk_delete' => $actor instanceof User && $actor->can('BULK_DELETE_BACKUPS'),
            'restore' => $actor instanceof User && $actor->can('RESTORE_BACKUPS'),
            'bulk_restore' => $actor instanceof User && $actor->can('BULK_RESTORE_BACKUPS'),
            'download' => $actor instanceof User && $actor->can('DOWNLOAD_BACKUPS'),
            'export' => $actor instanceof User && $actor->can('EXPORT_BACKUPS'),
            'view' => $actor instanceof User && $actor->can('VIEW_BACKUPS'),
        ];
    }

    /**
     * Domain refusals are input problems from the client's point of view:
     * the selected row moved, or it is the one archive the guard protects.
     */
    private function assertDomainOutcome(callable $operation): void
    {
        try {
            $operation();
        } catch (BackupNotFound|BackupDeletionRefused|BackupRestoreConflict $exception) {
            throw ValidationException::withMessages(['uuids' => $exception->getMessage()]);
        }
    }

    /**
     * `expectsJson()` is true for an XHR that accepts any content type; an
     * Inertia visit sends `X-Inertia` and `Accept: text/html`.
     */
    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() && ! $request->header('X-Inertia');
    }
}
