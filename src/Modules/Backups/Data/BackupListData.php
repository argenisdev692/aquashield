<?php

declare(strict_types=1);

namespace Modules\Backups\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Paginated inventory envelope for the read-only REST API.
 *
 * The rows are the same `BackupArchiveData` the web index returns, so the two
 * transports cannot drift into different field sets.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BackupListData extends Data
{
    /**
     * @param list<BackupArchiveData> $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $lastPage,
    ) {}
}
