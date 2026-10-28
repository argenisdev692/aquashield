<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Http\Controllers\Api;

use Illuminate\Http\Request;
use Modules\ActivityLog\Data\ActivityDetailData;
use Modules\ActivityLog\Data\ActivityEntryData;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Data\ActivityListData;
use Modules\ActivityLog\Models\ActivityEntry;
use Modules\ActivityLog\Queries\ActivityListQuery;

/**
 * Read-only REST surface for the audit trail. Scramble documents the Data
 * schemas; there is no write verb, by design.
 */
final readonly class ActivityLogController
{
    public function __construct(
        private ActivityListQuery $activity,
    ) {}

    public function index(Request $request): ActivityListData
    {
        $filters = ActivityFilterData::fromRequest($request);
        $page = $this->activity->paginate($filters);

        $rows = array_map(
            static fn (ActivityEntry $entry): ActivityEntryData => ActivityEntryData::fromModel($entry),
            $page->items(),
        );

        return new ActivityListData(
            rows: array_values($rows),
            total: $page->total(),
            page: $page->currentPage(),
            perPage: $page->perPage(),
        );
    }

    public function show(string $activity): ActivityDetailData
    {
        $entry = ActivityEntry::query()
            ->with('causer:id,first_name,last_name')
            ->where('uuid', $activity)
            ->first();

        if ($entry === null) {
            abort(404);
        }

        return new ActivityDetailData(ActivityEntryData::fromModel($entry));
    }
}
