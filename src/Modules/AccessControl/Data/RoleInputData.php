<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use Spatie\LaravelData\Data;

/**
 * Validated role input. The FormRequest owns transport rules; this object is
 * what the action receives, so an action never reads a Request.
 */
final class RoleInputData extends Data
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly array $permissions = [],
    ) {}
}
