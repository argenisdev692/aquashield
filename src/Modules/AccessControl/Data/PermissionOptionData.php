<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use App\Models\Permission;
use Modules\AccessControl\Support\PermissionName as PermissionNameSupport;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One checkbox in the role permission matrix.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PermissionOptionData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $group,
        public readonly bool $isSystem,
    ) {}

    public static function fromModel(Permission $permission): self
    {
        return new self(
            name: (string) $permission->name,
            group: PermissionNameSupport::group((string) $permission->name),
            isSystem: (bool) $permission->getAttribute('is_system'),
        );
    }
}
