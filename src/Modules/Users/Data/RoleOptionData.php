<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A role and the permission names it carries.
 *
 * The permission list is what makes the grant ceiling visible to the UI: an
 * assigner can see that ADMIN is above them before submitting and getting a
 * 422.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class RoleOptionData extends Data
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly array $permissions,
    ) {}
}
