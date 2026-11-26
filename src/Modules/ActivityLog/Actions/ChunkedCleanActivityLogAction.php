<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Actions;

use Illuminate\Database\Eloquent\Builder;
use Modules\ActivityLog\Models\ActivityEntry;
use Spatie\Activitylog\Actions\CleanActivityLogAction;

/**
 * Retention without a single multi-million-row DELETE.
 *
 * The default action issues one `where(created_at < cutoff)->delete()`; on a
 * seven-year audit table that is a long lock and a large WAL burst. This
 * subclass deletes 1 000 ids at a time, which keeps the same cutoff semantics
 * and lets the database breathe between batches. The query delete bypasses the
 * append-only model events on purpose.
 */
final class ChunkedCleanActivityLogAction extends CleanActivityLogAction
{
    private const int CHUNK = 1_000;

    protected function deleteOldActivities(string $cutOffDate, ?string $logName): int
    {
        $deleted = 0;

        do {
            $ids = ActivityEntry::query()
                ->where('created_at', '<', $cutOffDate)
                ->when(
                    $logName !== null,
                    static fn (Builder $query): Builder => $query->where('log_name', $logName),
                )
                // The predicate is on created_at, so order by it: the planner
                // can use activity_log_created_at_index instead of scanning the
                // primary key and sorting.
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deletedInBatch = ActivityEntry::query()
                ->whereIn('id', $ids)
                ->delete();

            $deleted += $deletedInBatch;

            // A trigger, constraint or partition that removes fewer rows than
            // the batch would otherwise re-select the same ids forever.
            if ($deletedInBatch === 0) {
                break;
            }
        } while ($ids->count() === self::CHUNK);

        return $deleted;
    }
}
