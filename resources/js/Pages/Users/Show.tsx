/**
 * Users/Show — the staff member's administrative control panel.
 *
 * Profile and access are separate forms because the server has separate
 * permissions, audit events and failure semantics for each. Lifecycle actions
 * (invite, suspend, delete) are one-click operations with a confirmation for
 * the destructive ones; every guard is re-checked server-side.
 */
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Calendar,
    KeyRound,
    Mail,
    Pencil,
    RotateCcw,
    Shield,
    ShieldOff,
    Trash2,
    UserRound,
    X,
    XCircle,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Tag } from '@/Components/ui/tag';
import CrudDialog from '@/Components/DataTable/CrudDialog';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/date';
import UserAccessPanel from './components/UserAccessPanel';
import UserStatusBadge from './components/UserStatusBadge';

interface ShowUserPageProps {
    user: Modules.Users.Data.ManagedUserData;
    catalog: Modules.Users.Data.UserAccessCatalogData;
    activeSuperAdminCount: number;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canSuspend: boolean;
    canActivate: boolean;
    canResendInvitation: boolean;
    canSendPasswordReset: boolean;
    canAssignRoles: boolean;
    canAssignPermissions: boolean;
    invitationExpiresInHours: number;
    passwordResetExpiresInMinutes: number;
}

const DetailRow = ({
    icon,
    label,
    children,
}: {
    icon: React.ReactNode;
    label: string;
    children: React.ReactNode;
}) => (
    <div className="flex items-start gap-3">
        <span className="mt-0.5 text-surface-400" aria-hidden="true">
            {icon}
        </span>
        <div className="min-w-0">
            <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">{label}</dt>
            <dd className="truncate text-sm text-surface-900 dark:text-surface-0">{children}</dd>
        </div>
    </div>
);

export default function UsersShow({
    user,
    catalog,
    activeSuperAdminCount,
    canUpdate,
    canDelete,
    canRestore,
    canSuspend,
    canActivate,
    canResendInvitation,
    canSendPasswordReset,
    canAssignRoles,
    canAssignPermissions,
    invitationExpiresInHours,
    passwordResetExpiresInMinutes,
}: ShowUserPageProps) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [confirmSuspend, setConfirmSuspend] = useState(false);
    const [confirmAccessLink, setConfirmAccessLink] = useState(false);
    const [sendingAccessLink, setSendingAccessLink] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);

    /*
     * Lifecycle writes are Inertia visits: a 422 (the account changed under
     * us, the invitation was already consumed) only reaches `onError`. The
     * dialog used to close and say nothing.
     */
    const reportFailure = (errors: Record<string, string>): void => {
        setActionError(Object.values(errors)[0] ?? 'The action could not be completed. Try again.');
    };

    const clearFailure = (): void => setActionError(null);

    const deleted = user.deleted_at !== null;
    const suspended = user.status === 'suspended';
    /*
     * The server capability, not a role name inferred in the browser. `roles`
     * is display data; `is_super_admin` is the same flag the policy and the
     * list screen use, so the guard cannot disagree with the server.
     */
    const isLastActiveAdmin =
        user.is_super_admin && user.status === 'active' && activeSuperAdminCount === 1;

    return (
        <AppLayout title="User profile">
            <Head title={user.name} />

            {/* No `mx-auto`: same left-edge alignment as the "User profile" title. */}
            <div className="flex max-w-4xl flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-0">
                                {user.name}
                            </h2>
                            <UserStatusBadge status={user.status} label={user.status_label} />
                            {deleted ? <Tag variant="destructive">Deleted</Tag> : null}
                        </div>
                        <p className="text-sm text-surface-600 dark:text-surface-300">
                            {user.email}
                            {user.username !== null ? ` · @${user.username}` : ''}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canUpdate && !deleted ? (
                            <Button
                                render={<Link href={`/users/${user.uuid}/edit`} />}
                                nativeButton={false}
                                variant="outline"
                                size="lg"
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Edit
                            </Button>
                        ) : null}

                        {canResendInvitation || canSendPasswordReset ? (
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => {
                                    clearFailure();
                                    setConfirmAccessLink(true);
                                }}
                                disabled={sendingAccessLink}
                            >
                                {canResendInvitation ? (
                                    <Mail className="size-4" aria-hidden="true" />
                                ) : (
                                    <KeyRound className="size-4" aria-hidden="true" />
                                )}
                                {canResendInvitation
                                    ? 'Resend invitation'
                                    : 'Send password-set link'}
                            </Button>
                        ) : null}

                        {canSuspend && user.status === 'active' && !deleted ? (
                            <Button
                                variant="warning"
                                size="lg"
                                onClick={() => {
                                    clearFailure();
                                    setConfirmSuspend(true);
                                }}
                                disabled={isLastActiveAdmin}
                                title={
                                    isLastActiveAdmin
                                        ? 'The last active super administrator cannot be suspended.'
                                        : undefined
                                }
                            >
                                <ShieldOff className="size-4" aria-hidden="true" />
                                Suspend
                            </Button>
                        ) : null}

                        {canActivate && suspended && !deleted ? (
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => {
                                    clearFailure();
                                    router.post(
                                        '/users/bulk-status',
                                        { uuids: [user.uuid], status: 'active' },
                                        {
                                            preserveScroll: true,
                                            onError: reportFailure,
                                        },
                                    );
                                }}
                            >
                                <Shield className="size-4" aria-hidden="true" />
                                Activate
                            </Button>
                        ) : null}

                        {canRestore && deleted ? (
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => {
                                    clearFailure();
                                    router.patch(
                                        `/users/${user.uuid}/restore`,
                                        {},
                                        {
                                            preserveScroll: true,
                                            onError: reportFailure,
                                        },
                                    );
                                }}
                            >
                                <RotateCcw className="size-4" aria-hidden="true" />
                                Restore
                            </Button>
                        ) : null}

                        {canDelete && !deleted ? (
                            <Button
                                variant="destructive"
                                size="lg"
                                onClick={() => {
                                    clearFailure();
                                    setConfirmDelete(true);
                                }}
                                disabled={isLastActiveAdmin}
                                title={
                                    isLastActiveAdmin
                                        ? 'The last active super administrator cannot be deleted.'
                                        : undefined
                                }
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                {actionError !== null ? (
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border border-aq-danger/30 bg-aq-danger/5 px-4 py-3 text-sm text-aq-danger-strong"
                    >
                        <XCircle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <p className="min-w-0 flex-1 font-medium">{actionError}</p>
                        <button
                            type="button"
                            onClick={clearFailure}
                            aria-label="Dismiss message"
                            className="grid size-6 shrink-0 place-items-center rounded-md text-current opacity-70 transition-opacity duration-150 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 motion-reduce:transition-none"
                        >
                            <X className="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Account</CardTitle>
                        <CardDescription>Lifecycle and contact details.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <DetailRow icon={<UserRound className="size-4" />} label="Full name">
                                {user.name}
                            </DetailRow>
                            <DetailRow icon={<Mail className="size-4" />} label="Email">
                                {user.email}
                            </DetailRow>
                            <DetailRow icon={<Shield className="size-4" />} label="Roles">
                                <span className="flex flex-wrap gap-1">
                                    {user.roles.length > 0
                                        ? user.roles.map((role) => (
                                              <Tag key={role} variant="outline">
                                                  {role}
                                              </Tag>
                                          ))
                                        : '—'}
                                </span>
                            </DetailRow>
                            <DetailRow icon={<Calendar className="size-4" />} label="Created">
                                {formatDateTime(user.created_at) || '—'}
                            </DetailRow>
                            <DetailRow icon={<Mail className="size-4" />} label="Email verified">
                                {user.email_verified ? 'Yes' : 'No'}
                            </DetailRow>
                            <DetailRow
                                icon={<KeyRound className="size-4" />}
                                label="Password change"
                            >
                                {user.must_change_password
                                    ? 'Required at next sign-in'
                                    : 'Not required'}
                            </DetailRow>
                            <DetailRow icon={<Calendar className="size-4" />} label="Invited">
                                {formatDateTime(user.invitation_sent_at) || '—'}
                            </DetailRow>
                            <DetailRow icon={<Calendar className="size-4" />} label="Activated">
                                {formatDateTime(user.activated_at) || '—'}
                            </DetailRow>
                            {user.suspended_at !== null ? (
                                <DetailRow
                                    icon={<ShieldOff className="size-4" />}
                                    label="Suspended"
                                >
                                    {formatDateTime(user.suspended_at) || '—'}
                                </DetailRow>
                            ) : null}
                        </dl>
                    </CardContent>
                </Card>

                <UserAccessPanel
                    user={user}
                    catalog={catalog}
                    canAssignRoles={canAssignRoles}
                    canAssignPermissions={canAssignPermissions}
                />

                <div className="pb-2">
                    <Button
                        render={<Link href="/users" />}
                        nativeButton={false}
                        variant="ghost"
                        size="lg"
                    >
                        Back to users
                    </Button>
                </div>
            </div>

            <CrudDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete ${user.name}?`}
                description="The account is soft-deleted and can be restored from the list. Its access is removed immediately."
                confirmLabel="Delete"
                confirmVariant="destructive"
                onConfirm={() => {
                    setConfirmDelete(false);
                    router.delete(`/users/${user.uuid}`, {
                        preserveScroll: true,
                        onError: reportFailure,
                    });
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">{user.email}</p>
            </CrudDialog>

            <CrudDialog
                open={confirmSuspend}
                onOpenChange={setConfirmSuspend}
                title={`Suspend ${user.name}?`}
                description="The account is signed out, its API tokens are revoked, and it cannot sign in until reactivated."
                confirmLabel="Suspend"
                confirmVariant="destructive"
                onConfirm={() => {
                    setConfirmSuspend(false);
                    router.post(
                        '/users/bulk-status',
                        { uuids: [user.uuid], status: 'suspended' },
                        {
                            preserveScroll: true,
                            onError: reportFailure,
                        },
                    );
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">{user.email}</p>
            </CrudDialog>
            <CrudDialog
                open={confirmAccessLink}
                onOpenChange={(open) => {
                    if (!sendingAccessLink) {
                        setConfirmAccessLink(open);
                    }
                }}
                title={
                    canResendInvitation
                        ? `Resend the invitation to ${user.name}?`
                        : `Send a password-set link to ${user.name}?`
                }
                description={
                    canResendInvitation
                        ? `A new activation link will be sent to ${user.email}. It expires in ${invitationExpiresInHours} hours and invalidates every older invitation.`
                        : `A password-set link will be sent to ${user.email}. It expires in ${passwordResetExpiresInMinutes} minutes and replaces any previous link.`
                }
                confirmLabel={
                    sendingAccessLink
                        ? 'Sending…'
                        : canResendInvitation
                          ? 'Send invitation'
                          : 'Send link'
                }
                loading={sendingAccessLink}
                onConfirm={() => {
                    setSendingAccessLink(true);
                    clearFailure();
                    router.post(
                        canResendInvitation
                            ? `/users/${user.uuid}/invitation`
                            : `/users/${user.uuid}/password-reset`,
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: () => setConfirmAccessLink(false),
                            onError: (errors) => {
                                reportFailure(errors);
                                setConfirmAccessLink(false);
                            },
                            onFinish: () => setSendingAccessLink(false),
                        },
                    );
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">{user.email}</p>
            </CrudDialog>
        </AppLayout>
    );
}
