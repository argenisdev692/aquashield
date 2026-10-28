<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Backups\Application\Repositories\BackupActivityLog;

final readonly class BackupActivityController
{
    public function __construct(
        private BackupActivityLog $activity,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 50);
        $limit = is_numeric($limit) ? (int) $limit : 50;

        return response()->json(['rows' => $this->activity->recent($limit)]);
    }
}
