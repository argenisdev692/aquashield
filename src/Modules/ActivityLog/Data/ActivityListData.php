<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class ActivityListData extends Data
{
    /**
     * @param list<ActivityEntryData> $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}
