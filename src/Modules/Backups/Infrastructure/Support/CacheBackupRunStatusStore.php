<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Support;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Modules\Backups\Application\Ports\BackupRunStatusStore;
use Modules\Backups\Data\BackupRunStatusData;
use Modules\Backups\Domain\Enums\BackupRunStatus;
use Throwable;

final class CacheBackupRunStatusStore implements BackupRunStatusStore
{
    private const string KEY = 'backups:database-run:status';

    private const int TTL_SECONDS = 7_200;

    public function current(): ?BackupRunStatusData
    {
        try {
            $payload = Cache::get(self::KEY);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $status = isset($payload['status']) && is_string($payload['status'])
            ? BackupRunStatus::tryFrom($payload['status'])
            : null;
        $updatedAt = isset($payload['updated_at']) && is_string($payload['updated_at'])
            ? $payload['updated_at']
            : null;

        return $status instanceof BackupRunStatus
            ? new BackupRunStatusData($status, $updatedAt)
            : null;
    }

    public function markQueued(): BackupRunStatusData
    {
        $status = new BackupRunStatusData(
            BackupRunStatus::Queued,
            (new DateTimeImmutable)->format(DATE_ATOM),
        );

        $this->write($status);

        return $status;
    }

    public function update(BackupRunStatus $status): void
    {
        $this->write(new BackupRunStatusData(
            $status,
            (new DateTimeImmutable)->format(DATE_ATOM),
        ));
    }

    private function write(BackupRunStatusData $status): void
    {
        try {
            Cache::put(self::KEY, [
                'status' => $status->status->value,
                'updated_at' => $status->updatedAt,
            ], self::TTL_SECONDS);
        } catch (Throwable $exception) {
            // Status is observability, not the backup operation. A cache outage
            // must never prevent a requested dump from running.
            report($exception);
        }
    }
}
