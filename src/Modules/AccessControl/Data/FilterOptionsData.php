<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Options for the two list screens. Both controllers receive the full option
 * set; a screen renders only the arrays its own filter bar uses.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class FilterOptionsData extends Data
{
    /**
     * @param list<string> $permissions
     * @param list<string> $roles
     * @param list<string> $groups
     */
    public function __construct(
        public readonly array $permissions = [],
        public readonly array $roles = [],
        public readonly array $groups = [],
    ) {}
}
