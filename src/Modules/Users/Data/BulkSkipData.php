<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One row a bulk action deliberately left untouched.
 *
 * A skip is not an error: bulk operations are transactional per accepted row
 * and report why a protected row was excluded instead of failing the whole
 * selection with an opaque 403.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BulkSkipData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $reason,
    ) {}
}
