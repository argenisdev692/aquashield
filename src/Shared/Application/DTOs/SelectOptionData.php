<?php

declare(strict_types=1);

namespace Shared\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One select option on the wire. Shared because every module's screens render
 * the same `{value, label}` shape; the values still come from the module enum.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class SelectOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
