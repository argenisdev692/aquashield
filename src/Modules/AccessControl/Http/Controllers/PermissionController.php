<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Response as InertiaResponse;
use Modules\AccessControl\Actions\CreatePermissionAction;
use Modules\AccessControl\Actions\DeletePermissionAction;
use Modules\AccessControl\Actions\UpdatePermissionAction;
use Modules\AccessControl\Data\PermissionData;
use Modules\AccessControl\Data\PermissionFilterData;
use Modules\AccessControl\Data\PermissionInputData;
use Modules\AccessControl\Http\Requests\StorePermissionRequest;
use Modules\AccessControl\Http\Requests\UpdatePermissionRequest;
use Modules\AccessControl\Queries\FilterOptionsQuery;
use Modules\AccessControl\Queries\PermissionListQuery;
use Shared\Domain\Enums\Permission as PermissionEnum;

/**
 * Admin CRUD for the permission catalogue. Permissions are one field, so the
 * Inertia screen owns create/edit dialogs; there is no separate create page.
 */
final readonly class PermissionController
{
    public function __construct(
        private PermissionListQuery $permissions,
        private FilterOptionsQuery $filterOptions,
        private CreatePermissionAction $createPermission,
        private UpdatePermissionAction $updatePermission,
        private DeletePermissionAction $deletePermission,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = PermissionFilterData::fromRequest($request);
        $page = $this->permissions->paginate($filters);
        $rows = PermissionData::collect($page->items());

        if ($this->wantsJson($request)) {
            return response()->json([
                'rows' => $rows,
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ]);
        }

        $actor = $this->actor($request);

        return inertia('AccessControl/Permissions/Index', [
            'rows' => $rows,
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions->execute(),
            'canCreate' => $actor->can(PermissionEnum::CreatePermission->value),
            'canUpdate' => $actor->can(PermissionEnum::UpdatePermission->value),
            'canDelete' => $actor->can(PermissionEnum::DeletePermission->value),
            'canExport' => $actor->can(PermissionEnum::ExportPermission->value),
            'canResetCache' => $actor->can(PermissionEnum::UpdatePermission->value),
        ]);
    }

    public function store(StorePermissionRequest $request): JsonResponse|RedirectResponse
    {
        $permission = $this->createPermission->execute(
            PermissionInputData::from($request->validated()),
            $this->actor($request),
        );

        if ($this->wantsJson($request)) {
            return response()->json(['permission' => PermissionData::fromModel($permission)], 201);
        }

        return to_route('permissions.index')->with('status', 'Permission created.');
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse|RedirectResponse
    {
        $updated = $this->updatePermission->execute(
            $permission,
            PermissionInputData::from($request->validated()),
            $this->actor($request),
        );

        if ($this->wantsJson($request)) {
            return response()->json(['permission' => PermissionData::fromModel($updated)]);
        }

        return to_route('permissions.index')->with('status', 'Permission updated.');
    }

    public function destroy(Request $request, Permission $permission): RedirectResponse|Response
    {
        $this->deletePermission->execute($permission, $this->actor($request));

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('permissions.index')->with('status', 'Permission deleted.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() && ! $request->header('X-Inertia');
    }
}
