<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use App\Models\Permission;
use Modules\AccessControl\Support\PermissionName as PermissionNameSupport;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One catalogue row for the permission list.
 *
 * `group` is derived from the name; there is no group column in the Spatie
 * table. Counts are populated by `withCount` in the list query.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PermissionData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $guardName,
        public readonly string $group,
        public readonly bool $isSystem,
        public readonly int $rolesCount,
        public readonly int $usersCount,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromModel(Permission $permission): self
    {
        $attributes = $permission->getAttributes();

        if (! array_key_exists('roles_count', $attributes) || ! array_key_exists('users_count', $attributes)) {
            $permission->loadCount(['roles', 'users']);
        }

        return new self(
            uuid: (string) $permission->uuid,
            name: (string) $permission->name,
            guardName: (string) $permission->guard_name,
            group: PermissionNameSupport::group((string) $permission->name),
            isSystem: (bool) $permission->getAttribute('is_system'),
            rolesCount: (int) $permission->getAttribute('roles_count'),
            usersCount: (int) $permission->getAttribute('users_count'),
            createdAt: $permission->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $permission->updated_at?->format('Y-m-d H:i:s'),
        );
    }
}
