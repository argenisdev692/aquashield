<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\ActivityLog\Data\ActivityEntryData;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Models\ActivityEntry;
use Modules\ActivityLog\Queries\ActivityListQuery;

final readonly class ActivityLogController
{
    public function __construct(
        private ActivityListQuery $activity,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = ActivityFilterData::fromRequest($request);
        $page = $this->activity->paginate($filters);
        $rows = ActivityEntryData::collect($page->items());
        $actor = $request->user();

        if ($this->wantsJson($request)) {
            return response()->json([
                'rows' => $rows,
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ]);
        }

        return inertia('ActivityLog/Index', [
            'rows' => $rows,
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'filters' => $filters,
            'canView' => $actor instanceof User && $actor->can('VIEW_ACTIVITY_LOGS'),
            'canExport' => $actor instanceof User && $actor->can('EXPORT_ACTIVITY_LOGS'),
        ]);
    }

    public function show(Request $request, string $activity): JsonResponse|InertiaResponse
    {
        $entry = ActivityEntry::query()
            ->with('causer:id,first_name,last_name')
            ->where('uuid', $activity)
            ->first();

        if ($entry === null) {
            abort(404);
        }

        $data = ActivityEntryData::fromModel($entry);

        if ($this->wantsJson($request)) {
            return response()->json(['activity' => $data]);
        }

        return inertia('ActivityLog/Show', [
            'activity' => $data,
        ]);
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
