<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use App\Models\Role;
use Shared\Domain\Enums\Role as RoleName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One role row. Counts are populated by `withCount` in `RoleListQuery`.
 *
 * A system role is one whose name the application checks in code; the
 * `Shared\Domain\Enums\Role` enum is the only definition. Other seeded roles
 * are ordinary data and may be renamed or retired.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class RoleData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $guardName,
        public readonly bool $isSystem,
        public readonly int $permissionsCount,
        public readonly int $usersCount,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromModel(Role $role): self
    {
        $attributes = $role->getAttributes();

        if (! array_key_exists('permissions_count', $attributes) || ! array_key_exists('users_count', $attributes)) {
            $role->loadCount(['permissions', 'users']);
        }

        return new self(
            uuid: (string) $role->uuid,
            name: (string) $role->name,
            guardName: (string) $role->guard_name,
            isSystem: self::isSystemName((string) $role->name),
            permissionsCount: (int) $role->getAttribute('permissions_count'),
            usersCount: (int) $role->getAttribute('users_count'),
            createdAt: $role->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $role->updated_at?->format('Y-m-d H:i:s'),
        );
    }

    public static function isSystemName(string $name): bool
    {
        return in_array($name, self::systemNames(), true);
    }

    /**
     * @return list<string>
     */
    public static function systemNames(): array
    {
        return [RoleName::SuperAdmin->value, RoleName::Admin->value];
    }
}
