<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Persistence;

use App\Models\User;
use Modules\Backups\Application\Repositories\BackupActivityLog;
use Modules\Backups\Data\BackupActivityData;
use Spatie\Activitylog\Models\Activity;

final readonly class SpatieBackupActivityLog implements BackupActivityLog
{
    /**
     * The audit write allowlist, mirrored on the read side. Even if another
     * writer puts a password or a signed URL in `properties`, the panel cannot
     * echo it back.
     */
    private const array SAFE_PROPERTIES = ['disk', 'filename', 'size_bytes', 'reason'];

    public function recent(int $limit = 50): array
    {
        $rows = Activity::query()
            ->with('causer:id,first_name,last_name')
            ->where('log_name', 'backups')
            ->latest()
            ->limit($this->limit($limit))
            ->get();

        return $rows->map(fn (Activity $activity): BackupActivityData => $this->toData($activity))->all();
    }

    public function forFilename(string $filename, int $limit = 20): array
    {
        $rows = Activity::query()
            ->with('causer:id,first_name,last_name')
            ->where('log_name', 'backups')
            ->where('properties->filename', $filename)
            ->latest()
            ->limit($this->limit($limit))
            ->get();

        return $rows->map(fn (Activity $activity): BackupActivityData => $this->toData($activity))->all();
    }

    private function toData(Activity $activity): BackupActivityData
    {
        $causer = $activity->causer;

        return new BackupActivityData(
            id: (string) $activity->getKey(),
            event: (string) $activity->event,
            description: (string) $activity->description,
            causerName: $causer instanceof User ? $causer->name : null,
            properties: $this->safeProperties($activity->properties?->all() ?? []),
            createdAt: $activity->created_at?->format('Y-m-d H:i') ?? '',
        );
    }

    private function limit(int $limit): int
    {
        return max(1, min($limit, 200));
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string|int>
     */
    private function safeProperties(array $properties): array
    {
        $safe = [];

        foreach (self::SAFE_PROPERTIES as $key) {
            $value = $properties[$key] ?? null;

            if (is_string($value) || is_int($value)) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
