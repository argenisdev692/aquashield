<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class AssignRolesData extends Data
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly array $roles,
    ) {}
}
