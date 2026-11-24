<?php

declare(strict_types=1);

namespace Modules\AccessControl\Queries;

use App\Models\Permission;
use Modules\AccessControl\Data\PermissionCatalogData;
use Modules\AccessControl\Data\PermissionOptionData;

/**
 * One query for the role permission matrix: the whole catalogue, one request.
 */
final class PermissionCatalogQuery
{
    public function execute(): PermissionCatalogData
    {
        /** @var list<PermissionOptionData> $permissions */
        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['name', 'is_system'])
            ->map(static fn (Permission $permission): PermissionOptionData => PermissionOptionData::fromModel($permission))
            ->values()
            ->all();

        return new PermissionCatalogData($permissions);
    }
}
