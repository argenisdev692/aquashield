<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response shape for the debounced username/email availability probe.
 *
 * The endpoint is a UX hint, not the authority; the typed shape keeps it in
 * the generated frontend contract and out of ad-hoc arrays.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class UserAvailabilityData extends Data
{
    public function __construct(
        public readonly bool $available,
        public readonly string $message,
    ) {}
}
