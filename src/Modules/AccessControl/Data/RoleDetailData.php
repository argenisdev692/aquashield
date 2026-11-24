<?php

declare(strict_types=1);

namespace Modules\AccessControl\Data;

use App\Models\Role;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The role edit payload: the row plus the permission names the matrix ticks.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class RoleDetailData extends Data
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $guardName,
        public readonly bool $isSystem,
        public readonly array $permissions,
        public readonly int $usersCount,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromModel(Role $role): self
    {
        $role->loadMissing('permissions:id,name');

        if (! array_key_exists('users_count', $role->getAttributes())) {
            $role->loadCount('users');
        }

        /*
         * Canonical order, not pivot order.
         *
         * The set is unordered by nature: `belongsToMany` has no `orderBy`, so
         * `pluck()` follows whatever order the join returns. The edit form
         * compares `permissions` with `isDirty` index-wise, and the matrix
         * re-sorts on every click — so an unordered payload makes the form
         * permanently dirty after the first toggle. Sorting here fixes every
         * consumer at once, instead of teaching each one to sort.
         */
        /** @var list<string> $permissions */
        $permissions = $role->permissions
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->sort()
            ->values()
            ->all();

        return new self(
            uuid: (string) $role->uuid,
            name: (string) $role->name,
            guardName: (string) $role->guard_name,
            isSystem: RoleData::isSystemName((string) $role->name),
            permissions: $permissions,
            usersCount: (int) $role->getAttribute('users_count'),
            createdAt: $role->created_at?->format('Y-m-d H:i:s'),
            updatedAt: $role->updated_at?->format('Y-m-d H:i:s'),
        );
    }
}
