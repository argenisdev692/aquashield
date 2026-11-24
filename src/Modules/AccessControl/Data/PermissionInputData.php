<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use Spatie\LaravelData\Data;

final class PermissionInputData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
