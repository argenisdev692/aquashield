<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Read-only options for the role/permission assignment screen.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class UserAccessCatalogData extends Data
{
    /**
     * @param list<RoleOptionData> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly array $roles,
        public readonly array $permissions,
    ) {}
}
