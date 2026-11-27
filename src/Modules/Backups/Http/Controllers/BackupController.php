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
use Modules\Backups\Application\Ports\BackupActivityLog;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Application\Ports\BackupRunStatusStore;
use Modules\Backups\Application\Queries\BackupIndexQuery;
use Modules\Backups\Data\BackupFilterData;
use Modules\Backups\Data\BackupRunStatusData;
use Modules\Backups\Domain\Exceptions\BackupDeletionRefused;
use Modules\Backups\Domain\Exceptions\BackupMoveInProgress;
use Modules\Backups\Domain\Exceptions\BackupNotFound;
use Modules\Backups\Domain\Exceptions\BackupRestoreConflict;
use Modules\Backups\Http\Requests\BulkDeleteBackupsRequest;
use Modules\Backups\Http\Requests\BulkRestoreBackupsRequest;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Ports\AuditPort;

final readonly class BackupController
{
    public function __construct(
        private BackupIndexQuery $backups,
        private BackupArchiveStore $archives,
        private BackupActivityLog $activity,
        private AuditPort $audit,
        private DeleteBackupsAction $deleteBackups,
        private RestoreBackupsAction $restoreBackups,
        private RunDatabaseBackupAction $runBackup,
        private BackupRunStatusStore $runStatus,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = BackupFilterData::fromRequest($request);
        $page = $this->backups->execute($filters);
        $actor = $request->user();
        $can = $this->abilities($actor);
        $runStatus = $this->runStatus->current() ?? BackupRunStatusData::idle();

        if ($this->wantsJson($request)) {
            return response()->json([
                'rows' => $page->items,
                'total' => $page->total,
                'page' => $page->page,
                'per_page' => $page->perPage,
                'last_page' => $page->lastPage,
                'filters' => $filters,
                'can' => $can,
                'run_status' => $runStatus,
            ]);
        }

        return inertia('Backups/Index', [
            'rows' => $page->items,
            'total' => $page->total,
            'currentPage' => $page->page,
            'perPage' => $page->perPage,
            'lastPage' => $page->lastPage,
            'filters' => $filters,
            'disks' => $this->archives->disks(),
            'can' => $can,
            'runStatus' => $runStatus,
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
            'quarantineDays' => $this->quarantineRetentionDays(),
            'can' => [
                'download' => $actor instanceof User
                    && $actor->can(PermissionName::DownloadBackup->value),
                'delete' => $actor instanceof User
                    && $actor->can(PermissionName::DeleteBackup->value)
                    && ! $archive->isTrashed
                    && ! $archive->isNewest,
                'restore' => $actor instanceof User
                    && $actor->can(PermissionName::RestoreBackup->value)
                    && $archive->isTrashed,
            ],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        // 202 means "a run is scheduled", not necessarily "a new job was
        // created": ShouldBeUnique absorbs a duplicate while one is active.
        $status = $this->runBackup->execute($request->user());

        return response()->json($status, 202);
    }

    public function runStatus(): JsonResponse
    {
        return response()->json($this->runStatus->current() ?? BackupRunStatusData::idle());
    }

    public function destroy(Request $request, string $backup): Response
    {
        $this->assertDomainOutcome(
            $request,
            fn () => $this->deleteBackups->execute([$backup], $request->user()),
        );

        return response()->noContent();
    }

    public function restore(Request $request, string $backup): Response
    {
        $this->assertDomainOutcome(
            $request,
            fn () => $this->restoreBackups->execute([$backup], $request->user()),
        );

        return response()->noContent();
    }

    public function bulkDelete(BulkDeleteBackupsRequest $request): Response
    {
        $uuids = BulkUuidsData::from($request->validated())->uuids;

        $this->assertDomainOutcome(
            $request,
            fn () => $this->deleteBackups->execute($uuids, $request->user()),
        );

        return response()->noContent();
    }

    public function bulkRestore(BulkRestoreBackupsRequest $request): Response
    {
        $uuids = BulkUuidsData::from($request->validated())->uuids;

        $this->assertDomainOutcome(
            $request,
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
            'create' => $actor instanceof User
                && $actor->can(PermissionName::CreateBackup->value),
            'delete' => $actor instanceof User
                && $actor->can(PermissionName::DeleteBackup->value),
            'bulk_delete' => $actor instanceof User
                && $actor->can(PermissionName::BulkDeleteBackup->value),
            'restore' => $actor instanceof User
                && $actor->can(PermissionName::RestoreBackup->value),
            'bulk_restore' => $actor instanceof User
                && $actor->can(PermissionName::BulkRestoreBackup->value),
            'download' => $actor instanceof User
                && $actor->can(PermissionName::DownloadBackup->value),
            'export' => $actor instanceof User
                && $actor->can(PermissionName::ExportBackup->value),
            'view' => $actor instanceof User
                && $actor->can(PermissionName::ViewBackup->value),
        ];
    }

    /**
     * The same window `backups:purge-quarantine` enforces. The page renders it
     * instead of hardcoding a number, so the copy cannot drift from the policy
     * it describes.
     */
    private function quarantineRetentionDays(): int
    {
        return max(0, (int) config('backup.quarantine.retain_days', 30));
    }

    /**
     * Domain refusals are input problems from the client's point of view:
     * the selected row moved, or it is the one archive the guard protects.
     * A refused destruction attempt is also audit-worthy (OWASP A09): the
     * guard is a security control, so trying to defeat it must leave a trace.
     */
    private function assertDomainOutcome(Request $request, callable $operation): void
    {
        try {
            $operation();
        } catch (BackupDeletionRefused $exception) {
            $this->audit->log('backup.delete_refused', null, [
                'filename' => $exception->filename,
                'reason' => $exception::class,
            ], $request->user(), 'backups');

            throw ValidationException::withMessages(['uuids' => $exception->getMessage()]);
        } catch (BackupRestoreConflict $exception) {
            $this->audit->log('backup.restore_conflict', null, [
                'filename' => $exception->filename,
                'reason' => $exception::class,
            ], $request->user(), 'backups');

            throw ValidationException::withMessages(['uuids' => $exception->getMessage()]);
        } catch (BackupMoveInProgress $exception) {
            throw ValidationException::withMessages(['uuids' => $exception->getMessage()]);
        } catch (BackupNotFound $exception) {
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
