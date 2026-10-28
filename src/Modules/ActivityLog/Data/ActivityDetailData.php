<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class ActivityDetailData extends Data
{
    public function __construct(
        public readonly ActivityEntryData $activity,
    ) {}
}
