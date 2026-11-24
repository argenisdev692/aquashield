<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The permission catalogue the role matrix renders. One query, one payload.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PermissionCatalogData extends Data
{
    /**
     * @param list<PermissionOptionData> $permissions
     */
    public function __construct(
        public readonly array $permissions,
    ) {}
}
