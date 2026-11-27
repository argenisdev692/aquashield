<?php

declare(strict_types=1);

namespace Modules\Backups\Data;

use DateTimeImmutable;
use Modules\Backups\Domain\Enums\BackupRunStatus;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class BackupRunStatusData extends Data
{
    public function __construct(
        public readonly BackupRunStatus $status,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function idle(): self
    {
        return new self(BackupRunStatus::Idle, (new DateTimeImmutable)->format(DATE_ATOM));
    }
}
