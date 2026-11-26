<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Modules\ActivityLog\Data\ActivityEntryData;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Queries\ActivityListQuery;
use Shared\Domain\Enums\Permission as PermissionName;
use Shared\Domain\Ports\AuditPort;

final readonly class ActivityLogController
{
    public function __construct(
        private ActivityListQuery $activity,
        private AuditPort $audit,
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
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
            ]);
        }

        return inertia('ActivityLog/Index', [
            'rows' => $rows,
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'canView' => $actor instanceof User && $actor->can(PermissionName::ViewActivityLog->value),
            'canExport' => $actor instanceof User && $actor->can(PermissionName::ExportActivityLog->value),
        ]);
    }

    public function show(Request $request, string $activity): JsonResponse|InertiaResponse
    {
        $entry = $this->activity->findByUuid($activity);

        if ($entry === null) {
            abort(404);
        }

        if (filter_var(config('activitylog.audit_views', false), FILTER_VALIDATE_BOOLEAN)) {
            // No subject: the explicit select does not hydrate the internal
            // `id`, and the viewed UUID is the public identifier the audit needs.
            $this->audit->log('activity.viewed', null, [
                'entry' => (string) $entry->uuid,
            ], $request->user(), 'activity');
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
