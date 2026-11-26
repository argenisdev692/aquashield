<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Persistence;

use App\Models\User;
use Modules\ActivityLog\Models\ActivityEntry;
use Modules\Backups\Application\Ports\BackupActivityLog;
use Modules\Backups\Data\BackupActivityData;
use Shared\Support\ActivityProperties;

final readonly class SpatieBackupActivityLog implements BackupActivityLog
{
    /**
     * `uuid` is the public identifier; the auto-increment `id` must never
     * cross HTTP, and `attribute_changes`/`subject_*` are not part of this
     * read model, so they are not selected.
     *
     * @var list<string>
     */
    private const array COLUMNS = [
        'uuid',
        'event',
        'description',
        'causer_type',
        'causer_id',
        'properties',
        'created_at',
    ];

    public function recent(int $limit = 50): array
    {
        $rows = ActivityEntry::query()
            ->select(self::COLUMNS)
            ->withCauser()
            ->where('log_name', 'backups')
            ->latest()
            ->limit($this->limit($limit))
            ->get();

        return $rows->map(fn (ActivityEntry $activity): BackupActivityData => $this->toData($activity))->all();
    }

    public function forFilename(string $filename, int $limit = 20): array
    {
        $rows = ActivityEntry::query()
            ->select(self::COLUMNS)
            ->withCauser()
            ->where('log_name', 'backups')
            ->where('properties->filename', $filename)
            ->latest()
            ->limit($this->limit($limit))
            ->get();

        return $rows->map(fn (ActivityEntry $activity): BackupActivityData => $this->toData($activity))->all();
    }

    private function toData(ActivityEntry $activity): BackupActivityData
    {
        $causer = $activity->causer;

        return new BackupActivityData(
            uuid: (string) $activity->uuid,
            event: (string) $activity->event,
            description: (string) $activity->description,
            causerName: $causer instanceof User ? $causer->name : null,
            properties: ActivityProperties::forBackupEvent($activity->properties?->all() ?? []),
            createdAt: $activity->created_at?->toIso8601String() ?? '',
        );
    }

    private function limit(int $limit): int
    {
        return max(1, min($limit, self::MAX_LIMIT));
    }
}
