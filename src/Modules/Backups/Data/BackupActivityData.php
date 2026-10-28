<?php

declare(strict_types=1);

namespace Modules\Backups\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One audit entry, already filtered by the adapter's property allowlist.
 *
 * `properties` never carries the raw activity payload: only the operational
 * fields the panel renders (disk, filename, size, reason).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BackupActivityData extends Data
{
    /**
     * @param array<string, string|int> $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $event,
        public readonly string $description,
        public readonly ?string $causerName,
        public readonly array $properties,
        public readonly string $createdAt,
    ) {}
}
