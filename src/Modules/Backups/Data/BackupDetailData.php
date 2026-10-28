<?php

declare(strict_types=1);

namespace Modules\Backups\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class BackupDetailData extends Data
{
    /**
     * @param list<BackupActivityData> $activities
     */
    public function __construct(
        public readonly BackupArchiveData $backup,
        public readonly array $activities,
    ) {}
}
