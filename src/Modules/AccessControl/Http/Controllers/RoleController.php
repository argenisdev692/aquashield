<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Response as InertiaResponse;
use Modules\AccessControl\Actions\CreateRoleAction;
use Modules\AccessControl\Actions\DeleteRoleAction;
use Modules\AccessControl\Actions\UpdateRoleAction;
use Modules\AccessControl\Data\RoleData;
use Modules\AccessControl\Data\RoleDetailData;
use Modules\AccessControl\Data\RoleFilterData;
use Modules\AccessControl\Data\RoleInputData;
use Modules\AccessControl\Http\Requests\StoreRoleRequest;
use Modules\AccessControl\Http\Requests\UpdateRoleRequest;
use Modules\AccessControl\Queries\FilterOptionsQuery;
use Modules\AccessControl\Queries\PermissionCatalogQuery;
use Modules\AccessControl\Queries\RoleListQuery;
use Shared\Domain\Enums\Permission as PermissionEnum;

/**
 * Admin CRUD for roles. Transport only: validate, invoke one action/query, map
 * to Data. JSON is the DataTable contract; an Inertia visit receives the same
 * first page as props.
 */
final readonly class RoleController
{
    public function __construct(
        private RoleListQuery $roles,
        private PermissionCatalogQuery $catalog,
        private FilterOptionsQuery $filterOptions,
        private CreateRoleAction $createRole,
        private UpdateRoleAction $updateRole,
        private DeleteRoleAction $deleteRole,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = RoleFilterData::fromRequest($request);
        $page = $this->roles->paginate($filters);
        $rows = RoleData::collect($page->items());

        if ($this->wantsJson($request)) {
            return response()->json([
                'rows' => $rows,
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
            ]);
        }

        $actor = $this->actor($request);

        return inertia('AccessControl/Roles/Index', [
            'rows' => $rows,
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions->execute(),
            'canCreate' => $actor->can(PermissionEnum::CreateRole->value),
            'canUpdate' => $actor->can(PermissionEnum::UpdateRole->value),
            'canDelete' => $actor->can(PermissionEnum::DeleteRole->value),
            'canExport' => $actor->can(PermissionEnum::ExportRole->value),
            'canResetCache' => $actor->can(PermissionEnum::UpdatePermission->value),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('AccessControl/Roles/Create', [
            'catalog' => $this->catalog->execute(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse|RedirectResponse
    {
        $role = $this->createRole->execute(
            RoleInputData::from($request->validated()),
            $this->actor($request),
        );

        if ($this->wantsJson($request)) {
            return response()->json(['role' => RoleData::fromModel($role)], 201);
        }

        return to_route('roles.index')->with('status', 'Role created.');
    }

    public function edit(Request $request, Role $role): JsonResponse|InertiaResponse
    {
        $role->load('permissions:id,name');
        $detail = RoleDetailData::fromModel($role);

        if ($this->wantsJson($request)) {
            return response()->json(['role' => $detail]);
        }

        return inertia('AccessControl/Roles/Edit', [
            'role' => $detail,
            'catalog' => $this->catalog->execute(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse|RedirectResponse
    {
        $updated = $this->updateRole->execute(
            $role,
            RoleInputData::from($request->validated()),
            $this->actor($request),
        );

        if ($this->wantsJson($request)) {
            return response()->json(['role' => RoleData::fromModel($updated)]);
        }

        return to_route('roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse|Response
    {
        $this->deleteRole->execute($role, $this->actor($request));

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('roles.index')->with('status', 'Role deleted.');
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
