<?php

declare(strict_types=1);

namespace Modules\AccessControl\Exports;

use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Data\RoleFilterData;
use Modules\AccessControl\Queries\RoleListQuery;
use Shared\Support\SpreadsheetCell;

/**
 * Row → column map for the role export. Streaming, drivers and PDF rendering
 * stay behind `Shared\Domain\Ports\ExportPort`; this class only transforms.
 *
 * The rows come from the same `RoleListQuery` as the index, so the export can
 * never drop a filter the screen applied.
 */
final readonly class RoleExportTransformer
{
    public function __construct(
        private RoleFilterData $filters,
        private RoleListQuery $roles,
        private int $limit,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'name' => 'Name',
            'type' => 'Type',
            'permissions_count' => 'Permissions',
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
        foreach ($this->roles->builder($this->filters)->limit($this->limit)->lazy(500) as $role) {
            yield [
                'name' => SpreadsheetCell::sanitise($role->name),
                'type' => SpreadsheetCell::sanitise(RoleData::isSystemName((string) $role->name) ? 'System' : 'Custom'),
                'permissions_count' => (int) $role->getAttribute('permissions_count'),
                'users_count' => (int) $role->getAttribute('users_count'),
                'created_at' => SpreadsheetCell::sanitise($role->created_at),
                'updated_at' => SpreadsheetCell::sanitise($role->updated_at),
            ];
        }
    }
}
