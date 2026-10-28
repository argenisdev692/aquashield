<?php

declare(strict_types=1);

namespace Modules\Users\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Response as InertiaResponse;
use Modules\Users\Actions\BulkDeleteUsersAction;
use Modules\Users\Actions\BulkRestoreUsersAction;
use Modules\Users\Actions\CreateUserAction;
use Modules\Users\Actions\DeleteUserAction;
use Modules\Users\Actions\RestoreUserAction;
use Modules\Users\Actions\SendPasswordResetLinkAction;
use Modules\Users\Actions\UpdateUserAction;
use Modules\Users\Data\CreateUserData;
use Modules\Users\Data\ManagedUserData;
use Modules\Users\Data\UpdateUserData;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Http\Requests\BulkDeleteUserRequest;
use Modules\Users\Http\Requests\BulkRestoreUserRequest;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Queries\UserAccessCatalogQuery;
use Modules\Users\Queries\UserListQuery;

/**
 * Admin CRUD for users.
 *
 * The controller is transport only: validate, invoke one action (or one read
 * query), map to Data. The JSON shape is the one the DataTable kit consumes
 * (`rows` + `total`); the same routes back the Inertia screens, and a write
 * redirects to the screen that shows the result.
 */
final readonly class UserController
{
    public function __construct(
        private UserListQuery $users,
        private UserAccessCatalogQuery $catalog,
        private CreateUserAction $createUser,
        private UpdateUserAction $updateUser,
        private DeleteUserAction $deleteUser,
        private RestoreUserAction $restoreUser,
        private SendPasswordResetLinkAction $sendPasswordResetLink,
        private BulkDeleteUsersAction $bulkDeleteUsers,
        private BulkRestoreUsersAction $bulkRestoreUsers,
    ) {}

    /**
     * One read, two transports. The JSON body is the DataTable kit's contract
     * (`rows` + `total`); an Inertia visit receives the same first page as
     * props, so the screen paints with data instead of a skeleton and a second
     * request. The filters are passed verbatim so a deep link initialises the
     * client table state with the filters the server already applied.
     */
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = UserFilterData::fromRequest($request);
        $page = $this->users->paginate($filters);

        if (! $this->wantsJson($request)) {
            $actor = $this->actor($request);

            return inertia('Users/Index', [
                'rows' => ManagedUserData::collect($page->items()),
                'total' => $page->total(),
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'filters' => $filters,
                'catalog' => $this->catalog->execute(),
                'currentUserUuid' => $actor->uuid,
                'canView' => $actor->can('VIEW_USER'),
                'canCreate' => $actor->can('create', User::class),
                'canUpdate' => $actor->can('UPDATE_USER'),
                'canDelete' => $actor->can('DELETE_USER'),
                'canRestore' => $actor->can('RESTORE_USER'),
                'canExport' => $actor->can('EXPORT_USER'),
                'canBulkDelete' => $actor->can('BULK_DELETE_USER'),
                'canBulkRestore' => $actor->can('BULK_RESTORE_USER'),
            ]);
        }

        return response()->json([
            'rows' => ManagedUserData::collect($page->items()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
        ]);
    }

    /**
     * The create screen. Role and permission options travel with the page so
     * the assigner can see what each role grants before submitting.
     */
    public function create(Request $request): InertiaResponse
    {
        $this->actor($request);

        return inertia('Users/Create', [
            'catalog' => $this->catalog->execute(),
            // Empty when the browser key is not configured; the field then
            // explains that the address must be entered manually.
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse|InertiaResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('view', $user);

        if ($this->wantsJson($request)) {
            return response()->json(ManagedUserData::fromModel($user));
        }

        return inertia('Users/Show', [
            'user' => ManagedUserData::fromModel($user),
            'catalog' => $this->catalog->execute(),
            // Row-level abilities, not just route permissions: the ceiling and
            // the last-super-admin guard mean two actors with UPDATE_USER can
            // legitimately see different buttons on the same row.
            'canUpdate' => Gate::forUser($actor)->allows('update', $user),
            'canDelete' => Gate::forUser($actor)->allows('delete', $user),
            'canRestore' => Gate::forUser($actor)->allows('restore', $user),
            'canAssignRoles' => Gate::forUser($actor)->allows('assignRoles', $user),
            'canAssignPermissions' => Gate::forUser($actor)->allows('assignPermissions', $user),
        ]);
    }

    /**
     * The edit screen. The route already requires UPDATE_USER; the policy adds
     * the ceiling check, so a MANAGER opening an ADMIN gets a 403 here rather
     * than a form that fails on submit.
     */
    public function edit(Request $request, User $user): InertiaResponse
    {
        Gate::forUser($this->actor($request))->authorize('update', $user);

        return inertia('Users/Edit', [
            'user' => ManagedUserData::fromModel($user),
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse|RedirectResponse
    {
        $user = $this->createUser->execute(
            CreateUserData::from($request->validated()),
            $this->actor($request),
        );

        if ($this->wantsJson($request)) {
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

        if ($this->wantsJson($request)) {
            return response()->json(ManagedUserData::fromModel($user));
        }

        return to_route('users.show', ['user' => $user->uuid]);
    }

    /**
     * Queue the same single-use password-set link used by the create flow.
     *
     * `UPDATE_USER` plus the policy ceiling authorise it; the broker's own
     * throttle still limits repeated sends, and the route adds a per-IP burst
     * limit.
     */
    public function sendPasswordReset(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->sendPasswordResetLink->execute($user, $this->actor($request));

        if ($this->wantsJson($request)) {
            return response()->json(['status' => 'queued'], 202);
        }

        return back()->with(
            'status',
            'A password-set link has been queued for '.$user->email.'.',
        );
    }

    public function destroy(Request $request, User $user): Response|RedirectResponse
    {
        $this->deleteUser->execute($user, $this->actor($request));

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('users.index');
    }

    public function restore(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $user = $this->restoreUser->execute($user, $this->actor($request));

        if ($this->wantsJson($request)) {
            return response()->json(ManagedUserData::fromModel($user));
        }

        return to_route('users.show', ['user' => $user->uuid]);
    }

    public function bulkDelete(BulkDeleteUserRequest $request): Response|RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated()['uuids'];

        $this->bulkDeleteUsers->execute($this->actor($request), $uuids);

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('users.index');
    }

    public function bulkRestore(BulkRestoreUserRequest $request): Response|RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated()['uuids'];

        $this->bulkRestoreUsers->execute($this->actor($request), $uuids);

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('users.index');
    }

    /**
     * `expectsJson()` is true for an XHR that accepts any content type; an
     * Inertia visit sends `X-Inertia` and `Accept: text/html`, so the two
     * transports never claim the same request.
     */
    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() && ! $request->header('X-Inertia');
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
