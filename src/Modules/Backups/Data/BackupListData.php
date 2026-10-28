<?php

declare(strict_types=1);

namespace Modules\Backups\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * REST list contract. The API returns a Data object so Scramble documents the
 * schema instead of guessing at an array shape.
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
