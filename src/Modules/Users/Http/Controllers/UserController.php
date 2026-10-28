<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Users\Actions\BulkDeleteUsersAction;
use Modules\Users\Actions\BulkRestoreUsersAction;
use Modules\Users\Actions\CreateUserAction;
use Modules\Users\Actions\DeleteUserAction;
use Modules\Users\Actions\RestoreUserAction;
use Modules\Users\Actions\UpdateUserAction;
use Modules\Users\Data\CreateUserData;
use Modules\Users\Data\ManagedUserData;
use Modules\Users\Data\UpdateUserData;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Http\Requests\BulkDeleteUserRequest;
use Modules\Users\Http\Requests\BulkRestoreUserRequest;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Queries\UserListQuery;

/**
 * Admin CRUD for users.
 *
 * The controller is transport only: validate, invoke one action (or one read
 * query), map to Data. The JSON shape is the one the DataTable kit consumes
 * (`rows` + `total`); browsers get a redirect after a write so the same routes
 * can back the Inertia screens when they land.
 */
final readonly class UserController
{
    public function __construct(
        private UserListQuery $users,
        private CreateUserAction $createUser,
        private UpdateUserAction $updateUser,
        private DeleteUserAction $deleteUser,
        private RestoreUserAction $restoreUser,
        private BulkDeleteUsersAction $bulkDeleteUsers,
        private BulkRestoreUsersAction $bulkRestoreUsers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = UserFilterData::fromRequest($request);
        $page = $this->users->paginate($filters);

        return response()->json([
            'rows' => ManagedUserData::collect($page->items()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('view', $user);

        return response()->json(ManagedUserData::fromModel($user));
    }

    public function store(StoreUserRequest $request): JsonResponse|RedirectResponse
    {
        $user = $this->createUser->execute(
            CreateUserData::from($request->validated()),
            $this->actor($request),
        );

        if ($request->expectsJson()) {
            return response()->json(ManagedUserData::fromModel($user), 201);
        }

        return to_route('users.show', ['user' => $user->uuid]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $user = $this->updateUser->execute(
            UpdateUserData::from($request->validated()),
            $user,
            $this->actor($request),
        );

        if ($request->expectsJson()) {
            return response()->json(ManagedUserData::fromModel($user));
        }

        return to_route('users.show', ['user' => $user->uuid]);
    }

    public function destroy(Request $request, User $user): Response|RedirectResponse
    {
        $this->deleteUser->execute($user, $this->actor($request));

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return to_route('users.index');
    }

    public function restore(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $user = $this->restoreUser->execute($user, $this->actor($request));

        if ($request->expectsJson()) {
            return response()->json(ManagedUserData::fromModel($user));
        }

        return to_route('users.show', ['user' => $user->uuid]);
    }

    public function bulkDelete(BulkDeleteUserRequest $request): Response|RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated()['uuids'];

        $this->bulkDeleteUsers->execute($this->actor($request), $uuids);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return to_route('users.index');
    }

    public function bulkRestore(BulkRestoreUserRequest $request): Response|RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated()['uuids'];

        $this->bulkRestoreUsers->execute($this->actor($request), $uuids);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return to_route('users.index');
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
