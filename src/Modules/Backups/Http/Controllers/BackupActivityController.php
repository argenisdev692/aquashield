<?php

declare(strict_types=1);

namespace Modules\Backups\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Backups\Application\Ports\BackupActivityLog;

final readonly class BackupActivityController
{
    public function __construct(
        private BackupActivityLog $activity,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.BackupActivityLog::MAX_LIMIT],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        return response()->json(['rows' => $this->activity->recent($limit)]);
    }
}
