<?php

declare(strict_types=1);

namespace Modules\AccessControl\Exports;

use Modules\AccessControl\Data\PermissionFilterData;
use Modules\AccessControl\Queries\PermissionListQuery;
use Modules\AccessControl\Support\PermissionName;
use Shared\Support\SpreadsheetCell;

/**
 * Row → column map for the permission export.
 */
final readonly class PermissionExportTransformer
{
    public function __construct(
        private PermissionFilterData $filters,
        private PermissionListQuery $permissions,
        private int $limit,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'name' => 'Name',
            'group' => 'Group',
            'type' => 'Type',
            'roles_count' => 'Roles',
            'users_count' => 'Users',
            'created_at' => 'Created at',
            'updated_at' => 'Updated at',
        ];
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return array_values($this->columns());
    }

    /**
     * @return iterable<int, array<string, string|int|float>>
     */
    public function rows(): iterable
    {
        /*
         * The controller checks the count before streaming; this limit is the
         * race guard. A row inserted between the count and the stream must not
         * push the file past the format ceiling.
         */
        foreach ($this->permissions->builder($this->filters)->limit($this->limit)->lazy(500) as $permission) {
            yield [
                'name' => SpreadsheetCell::sanitise($permission->name),
                'group' => SpreadsheetCell::sanitise(PermissionName::group((string) $permission->name)),
                'type' => SpreadsheetCell::sanitise((bool) $permission->getAttribute('is_system') ? 'System' : 'Custom'),
                'roles_count' => (int) $permission->getAttribute('roles_count'),
                'users_count' => (int) $permission->getAttribute('users_count'),
                'created_at' => SpreadsheetCell::sanitise($permission->created_at),
                'updated_at' => SpreadsheetCell::sanitise($permission->updated_at),
            ];
        }
    }
}
