<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The one response shape every Users bulk action returns.
 *
 * `updated` is the number of rows the action actually changed (for a resend,
 * the number of invitations queued). `skipped` carries the protected or
 * ineligible rows with a human reason, so the UI can say "3 updated, 1 skipped
 * because it is your own account" rather than silently doing less than asked.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BulkActionResultData extends Data
{
    /**
     * @param list<BulkSkipData> $skipped
     */
    public function __construct(
        public readonly int $updated,
        public readonly array $skipped = [],
    ) {}

    public function skippedCount(): int
    {
        return count($this->skipped);
    }
}
