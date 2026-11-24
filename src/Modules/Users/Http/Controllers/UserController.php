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
use Modules\Users\Actions\AssignRoleToUsersAction;
use Modules\Users\Actions\CreateUserAction;
use Modules\Users\Actions\DeleteUsersAction;
use Modules\Users\Actions\ResendInvitationAction;
use Modules\Users\Actions\ResendInvitationsAction;
use Modules\Users\Actions\RestoreUsersAction;
use Modules\Users\Actions\SendPasswordResetLinkAction;
use Modules\Users\Actions\UpdateUserAction;
use Modules\Users\Actions\UpdateUsersStatusAction;
use Modules\Users\Data\BulkActionResultData;
use Modules\Users\Data\CreateUserData;
use Modules\Users\Data\ManagedUserData;
use Modules\Users\Data\UpdateUserData;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Enums\UserStatus;
use Modules\Users\Http\Requests\BulkAssignRoleRequest;
use Modules\Users\Http\Requests\BulkDeleteUserRequest;
use Modules\Users\Http\Requests\BulkResendInvitationRequest;
use Modules\Users\Http\Requests\BulkRestoreUserRequest;
use Modules\Users\Http\Requests\BulkUpdateStatusRequest;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Queries\UserAccessCatalogQuery;
use Modules\Users\Queries\UserListQuery;
use Modules\Users\Support\AccessCeiling;
use Modules\Users\Support\InvitationLink;
use Shared\Domain\Enums\Permission as PermissionName;

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
        private DeleteUsersAction $deleteUsers,
        private RestoreUsersAction $restoreUsers,
        private SendPasswordResetLinkAction $sendPasswordResetLink,
        private ResendInvitationAction $resendInvitation,
        private UpdateUsersStatusAction $updateUsersStatus,
        private AssignRoleToUsersAction $assignRoleToUsers,
        private ResendInvitationsAction $resendInvitations,
        private AccessCeiling $ceiling,
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
                'activeSuperAdminCount' => $this->ceiling->activeSuperAdminCount(),
                'canView' => $actor->can(PermissionName::ViewUser->value),
                'canCreate' => $actor->can('create', User::class),
                'canUpdate' => $actor->can(PermissionName::UpdateUser->value),
                'canManageStatus' => $actor->can(PermissionName::UpdateUser->value),
                'canAssignRoles' => $actor->can(PermissionName::AssignRolesUser->value),
                'canDelete' => $actor->can(PermissionName::DeleteUser->value),
                'canRestore' => $actor->can(PermissionName::RestoreUser->value),
                'canExport' => $actor->can(PermissionName::ExportUser->value),
                'canBulkDelete' => $actor->can(PermissionName::BulkDeleteUser->value),
                'canBulkRestore' => $actor->can(PermissionName::BulkRestoreUser->value),
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
            'activeSuperAdminCount' => $this->ceiling->activeSuperAdminCount(),
            // Row-level abilities, not just route permissions: the ceiling and
            // the last-super-admin guard mean two actors with UPDATE_USER can
            // legitimately see different buttons on the same row.
            'canUpdate' => Gate::forUser($actor)->allows('update', $user),
            'canDelete' => Gate::forUser($actor)->allows('delete', $user),
            'canRestore' => Gate::forUser($actor)->allows('restore', $user),
            'canSuspend' => Gate::forUser($actor)->allows('suspend', $user),
            'canActivate' => Gate::forUser($actor)->allows('activate', $user),
            // Access-link capabilities are state-specific and mutually
            // exclusive: invited → invitation, active → password-set link,
            // suspended/deleted → none. The server owns the rule; the page only
            // renders the boolean it receives.
            'canResendInvitation' => $user->isInvited()
                && Gate::forUser($actor)->allows('resendInvitation', $user),
            'canSendPasswordReset' => $user->isActive()
                && ! $user->trashed()
                && Gate::forUser($actor)->allows('update', $user),
            'canAssignRoles' => Gate::forUser($actor)->allows('assignRoles', $user),
            'canAssignPermissions' => Gate::forUser($actor)->allows('assignPermissions', $user),
            // The confirmation dialog quotes the real TTLs so the copy cannot
            // drift from the job / broker configuration.
            'invitationExpiresInHours' => InvitationLink::EXPIRES_IN_HOURS,
            'passwordResetExpiresInMinutes' => (int) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60,
            ),
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
            CreateUserData::from($request->safe()->except(['temporary_password_confirmation'])),
            $this->actor($request),
        );

        if ($this->wantsJson($request)) {
            return response()->json(ManagedUserData::fromModel($user), 201);
        }

        return to_route('users.show', ['user' => $user->uuid])->with(
            'status',
            $user->isInvited()
                ? 'Account created. The activation email has been queued.'
                : 'Account created with a temporary password. The user must change it at first sign-in.',
        );
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

        return to_route('users.show', ['user' => $user->uuid])
            ->with('status', 'User updated.');
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

    /**
     * Rotates the invitation timestamp and queues a fresh signed link. The old
     * link stops working immediately, even though its signature is still valid.
     */
    public function resendInvitation(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->resendInvitation->execute($user, $this->actor($request));

        if ($this->wantsJson($request)) {
            return response()->json(['status' => 'queued'], 202);
        }

        return back()->with(
            'status',
            'A new activation link has been queued for '.$user->email.'.',
        );
    }

    public function destroy(Request $request, User $user): Response|RedirectResponse
    {
        $this->deleteUsers->execute($this->actor($request), [$user->uuid]);

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        // `back()` keeps the caller's URL — the filtered page, the page number —
        // so deleting a row from a filtered view no longer resets the table.
        return back()->with('status', 'User deleted.');
    }

    public function restore(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $restored = $this->restoreUsers->execute($this->actor($request), [$user->uuid])->firstOrFail();

        if ($this->wantsJson($request)) {
            return response()->json(ManagedUserData::fromModel($restored));
        }

        return to_route('users.show', ['user' => $restored->uuid])
            ->with('status', 'User restored.');
    }

    public function bulkDelete(BulkDeleteUserRequest $request): Response|RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated()['uuids'];

        $this->deleteUsers->execute($this->actor($request), $uuids);

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('users.index')->with('status', 'Selected users deleted.');
    }

    public function bulkRestore(BulkRestoreUserRequest $request): Response|RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated()['uuids'];

        $this->restoreUsers->execute($this->actor($request), $uuids);

        if ($this->wantsJson($request)) {
            return response()->noContent();
        }

        return to_route('users.index')->with('status', 'Selected users restored.');
    }

    public function bulkStatus(BulkUpdateStatusRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{uuids: list<string>, status: string} $data */
        $data = $request->validated();

        $result = $this->updateUsersStatus->execute(
            $this->actor($request),
            $data['uuids'],
            UserStatus::from($data['status']),
        );

        if ($this->wantsJson($request)) {
            return response()->json($result);
        }

        return back()->with('status', $this->summary($result, 'account updated', 'accounts updated'));
    }

    public function bulkAssignRole(BulkAssignRoleRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{uuids: list<string>, role: string} $data */
        $data = $request->validated();

        $result = $this->assignRoleToUsers->execute(
            $this->actor($request),
            $data['uuids'],
            $data['role'],
        );

        if ($this->wantsJson($request)) {
            return response()->json($result);
        }

        return back()->with('status', $this->summary($result, 'role updated', 'roles updated'));
    }

    public function bulkResendInvitations(BulkResendInvitationRequest $request): JsonResponse|RedirectResponse
    {
        /** @var array{uuids: list<string>} $data */
        $data = $request->validated();

        $result = $this->resendInvitations->execute(
            $this->actor($request),
            $data['uuids'],
        );

        if ($this->wantsJson($request)) {
            return response()->json($result);
        }

        return back()->with('status', $this->summary($result, 'invitation sent', 'invitations sent'));
    }

    private function summary(BulkActionResultData $result, string $singular, string $plural): string
    {
        $message = $result->updated.' '.($result->updated === 1 ? $singular : $plural).'.';

        if ($result->skipped !== []) {
            $message .= ' '.$result->skippedCount().' skipped.';
        }

        return $message;
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
