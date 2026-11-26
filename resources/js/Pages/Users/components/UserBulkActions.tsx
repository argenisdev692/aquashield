/**
 * Contextual bulk-action controls for the Users table.
 *
 * The component only decides which endpoint to call and which confirmation to
 * show. Eligibility (self, last active super admin, invited vs active) is
 * re-checked by the server and returned as a skip summary.
 *
 * Success and error messages are owned by the page (`onFeedback`), not by this
 * bar: a successful action reloads the rows, the kit clears the selection and
 * this component unmounts — a message rendered here would disappear unseen.
 */
import { useState } from 'react';
import { KeyRound, RotateCcw, ShieldCheck, ShieldOff, Trash2, UserCog } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import CrudDialog from '@/Components/DataTable/CrudDialog';
import SelectField from '@/Components/Forms/SelectField';
import { useUserBulkActions, type BulkFeedback } from '@/Modules/Users/hooks/useUserBulkActions';

/** The generated DTO is the only owner of these fields — no hand-written mirror. */
type BulkUserRow = Pick<
    Modules.Users.Data.ManagedUserData,
    'uuid' | 'name' | 'status' | 'deleted_at' | 'is_super_admin'
>;

interface UserBulkActionsProps {
    rows: readonly BulkUserRow[];
    onFeedback: (feedback: BulkFeedback) => void;
    roles: readonly string[];
    currentUserUuid: string;
    activeSuperAdminCount: number;
    canAssignRoles: boolean;
    canDelete: boolean;
    canRestore: boolean;
    /** Hides Activate/Suspend for a viewer who cannot change status. */
    canManageStatus?: boolean;
    /** Hides invite resends for a viewer who cannot send them. */
    canInvite?: boolean;
}

export default function UserBulkActions({
    rows,
    onFeedback,
    roles,
    currentUserUuid,
    activeSuperAdminCount,
    canAssignRoles,
    canDelete,
    canRestore,
    canManageStatus = true,
    canInvite = true,
}: UserBulkActionsProps) {
    const bulk = useUserBulkActions({ onFeedback });
    const [confirmSuspend, setConfirmSuspend] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [roleDialog, setRoleDialog] = useState(false);
    const [selectedRole, setSelectedRole] = useState<string | null>(null);

    const invitedRows = rows.filter((row) => row.status === 'invited');
    const deletedRows = rows.filter((row) => row.deleted_at !== null);
    const selectionIncludesProtected = rows.some(
        (row) =>
            row.uuid === currentUserUuid ||
            (row.is_super_admin && row.status === 'active' && activeSuperAdminCount === 1),
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            {canManageStatus ? (
                <>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8"
                        disabled={bulk.processing}
                        onClick={() => {
                            bulk.changeStatus(rows, 'active');
                        }}
                    >
                        <ShieldCheck className="mr-1.5 size-3.5" aria-hidden="true" />
                        Activate
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="warning"
                        className="h-8"
                        disabled={bulk.processing || selectionIncludesProtected}
                        title={
                            selectionIncludesProtected
                                ? 'Deselect your own account or the last active super administrator.'
                                : undefined
                        }
                        onClick={() => setConfirmSuspend(true)}
                    >
                        <ShieldOff className="mr-1.5 size-3.5" aria-hidden="true" />
                        Suspend
                    </Button>
                </>
            ) : null}

            {canAssignRoles ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-8"
                    disabled={bulk.processing || selectionIncludesProtected}
                    title={
                        selectionIncludesProtected
                            ? 'Deselect your own account or the last active super administrator.'
                            : undefined
                    }
                    onClick={() => setRoleDialog(true)}
                >
                    <UserCog className="mr-1.5 size-3.5" aria-hidden="true" />
                    Assign role
                </Button>
            ) : null}

            {canInvite ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-8"
                    disabled={bulk.processing || invitedRows.length === 0}
                    title={
                        invitedRows.length === 0
                            ? 'Select at least one invited account.'
                            : undefined
                    }
                    onClick={() => {
                        bulk.resendInvitations(invitedRows);
                    }}
                >
                    <KeyRound className="mr-1.5 size-3.5" aria-hidden="true" />
                    Resend invites
                </Button>
            ) : null}

            {canRestore && deletedRows.length > 0 ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-8"
                    disabled={bulk.processing}
                    onClick={() => {
                        bulk.restoreRows(deletedRows);
                    }}
                >
                    <RotateCcw className="mr-1.5 size-3.5" aria-hidden="true" />
                    Restore
                </Button>
            ) : null}

            {canDelete ? (
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    className="h-8"
                    disabled={bulk.processing || selectionIncludesProtected}
                    title={
                        selectionIncludesProtected
                            ? 'Deselect your own account or the last active super administrator.'
                            : undefined
                    }
                    onClick={() => setConfirmDelete(true)}
                >
                    <Trash2 className="mr-1.5 size-3.5" aria-hidden="true" />
                    Delete
                </Button>
            ) : null}

            {selectionIncludesProtected ? (
                <p className="basis-full text-xs font-medium text-surface-600 dark:text-surface-300">
                    Your own account and the last active super administrator cannot be suspended or
                    deleted. Deselect them to continue.
                </p>
            ) : null}

            <CrudDialog
                open={confirmSuspend}
                onOpenChange={setConfirmSuspend}
                title={`Suspend ${rows.length} ${rows.length === 1 ? 'account' : 'accounts'}?`}
                description="Sessions and API tokens are revoked immediately. The accounts can be reactivated later."
                confirmLabel="Suspend"
                confirmVariant="destructive"
                loading={bulk.processing}
                onConfirm={() => {
                    bulk.changeStatus(rows, 'suspended');
                    setConfirmSuspend(false);
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    {rows.map((row) => row.name).join(', ')}
                </p>
            </CrudDialog>

            <CrudDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title={`Delete ${rows.length} ${rows.length === 1 ? 'account' : 'accounts'}?`}
                description="The accounts are soft-deleted and can be restored later. Access is removed immediately."
                confirmLabel="Delete"
                confirmVariant="destructive"
                loading={bulk.processing}
                onConfirm={() => {
                    bulk.deleteRows(rows);
                    setConfirmDelete(false);
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    {rows.map((row) => row.name).join(', ')}
                </p>
            </CrudDialog>

            <CrudDialog
                open={roleDialog}
                onOpenChange={(open) => {
                    setRoleDialog(open);

                    if (!open) {
                        setSelectedRole(null);
                    }
                }}
                title="Assign a role"
                description={`The selected ${rows.length} ${rows.length === 1 ? 'account' : 'accounts'} will have their role set replaced.`}
                confirmLabel="Assign role"
                loading={bulk.processing}
                confirmDisabled={selectedRole === null}
                onConfirm={() => {
                    if (selectedRole !== null) {
                        bulk.assignRole(rows, selectedRole);
                    }

                    setRoleDialog(false);
                    setSelectedRole(null);
                }}
            >
                <SelectField
                    label="Role"
                    name="bulk_role"
                    value={selectedRole}
                    onChange={setSelectedRole}
                    options={roles.map((role) => ({ value: role, label: role }))}
                    placeholder="Choose a role"
                    required
                />
            </CrudDialog>
        </div>
    );
}
