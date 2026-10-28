<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Actions\AssignPermissionsAction;
use Modules\Users\Actions\AssignRolesAction;
use Modules\Users\Data\AssignPermissionsData;
use Modules\Users\Data\AssignRolesData;
use Modules\Users\Data\ManagedUserData;
use Modules\Users\Http\Requests\AssignPermissionsRequest;
use Modules\Users\Http\Requests\AssignRolesRequest;
use Modules\Users\Queries\UserAccessCatalogQuery;

/**
 * The two access surfaces are deliberately separate: a role assignment and a
 * direct-permission assignment have different permissions, different audit
 * events and different failure semantics. A single "save access" endpoint
 * would blur all three.
 */
final readonly class UserAccessController
{
    public function __construct(
        private UserAccessCatalogQuery $catalog,
        private AssignRolesAction $assignRoles,
        private AssignPermissionsAction $assignPermissions,
    ) {}

    public function catalog(): JsonResponse
    {
        return response()->json($this->catalog->execute());
    }

    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        $data = AssignRolesData::from($request->validated());

        $user = $this->assignRoles->execute($user, $this->actor($request), $data->roles);

        return response()->json(ManagedUserData::fromModel($user));
    }

    public function assignPermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        $data = AssignPermissionsData::from($request->validated());

        $user = $this->assignPermissions->execute($user, $this->actor($request), $data->permissions);

        return response()->json(ManagedUserData::fromModel($user));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return $actor;
    }
}
