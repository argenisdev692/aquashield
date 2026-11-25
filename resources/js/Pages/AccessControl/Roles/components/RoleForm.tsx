/**
 * RoleForm — the create and edit surface for one role.
 *
 * The name is immutable for system roles; the permission set is always
 * editable because that is how an operator repairs SUPER_ADMIN. The matrix is a
 * controlled field of the same Inertia form, so one submit writes one role.
 */
import { router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import CrudDialog from '@/Components/DataTable/CrudDialog';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import StickyFormBar from '@/Components/Forms/StickyFormBar';
import TextField from '@/Components/Forms/TextField';
import { useUnsavedChangesWarning, useZodForm } from '@/lib/forms';
import { useNavigationGuard } from '@/lib/leave-guard';
import { accessControlUrls } from '@/Modules/AccessControl/endpoints';
import { roleSchema, type RoleForm as RoleFormValues } from '@/Modules/AccessControl/schemas/role';
import PermissionMatrix from './PermissionMatrix';

interface RoleFormProps {
    role?: Modules.AccessControl.Data.RoleDetailData;
    catalog: Modules.AccessControl.Data.PermissionCatalogData;
    returnTo?: string | null;
}

const initialValues = (role: RoleFormProps['role'], returnTo: string): RoleFormValues => ({
    name: role?.name ?? '',
    permissions: role?.permissions ?? [],
    return_to: returnTo,
});

export default function RoleForm({ role, catalog, returnTo = null }: RoleFormProps) {
    const { form, errors, submit, touch, clearError } = useZodForm({
        initial: initialValues(role, returnTo ?? ''),
        schema: roleSchema,
    });

    useUnsavedChangesWarning(form.isDirty);
    const navigationGuard = useNavigationGuard(form.isDirty);

    const systemRole = role?.is_system === true;
    const summary = `${form.data.permissions.length} permission${
        form.data.permissions.length === 1 ? '' : 's'
    } · ${form.data.name || 'unnamed role'}${systemRole ? ' · system role' : ''}`;

    const onSubmit = (event: React.FormEvent): void => {
        event.preventDefault();

        submit(() => {
            if (role) {
                form.put(accessControlUrls.role(role.uuid), { preserveScroll: true });

                return;
            }

            form.post(accessControlUrls.roles, { preserveScroll: true });
        });
    };

    return (
        <>
            <form onSubmit={onSubmit} className="mx-auto flex max-w-6xl flex-col gap-6" noValidate>
                <FormErrorSummary
                    errors={errors}
                    labels={{ name: 'Role name', permissions: 'Permissions' }}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Identity</CardTitle>
                        <CardDescription>
                            The guard is fixed to <code>web</code>. Names are uppercase identifiers
                            so a route middleware and this screen cannot drift apart.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TextField
                            label="Role name"
                            name="name"
                            value={form.data.name}
                            onChange={(value) => {
                                form.setData('name', value.toUpperCase());
                                clearError('name');
                            }}
                            onBlur={() => touch('name')}
                            error={errors.name}
                            hint={
                                systemRole
                                    ? 'System role names are fixed. The permission set is still editable.'
                                    : 'Example: CLAIMS_REVIEWER. Letters, digits and underscores only.'
                            }
                            readOnly={systemRole}
                            required
                            maxLength={64}
                            autoCapitalize="characters"
                            spellCheck={false}
                            autoFocus={role === undefined}
                            disabled={form.processing}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-aq-accent" aria-hidden="true" />
                            Permissions
                        </CardTitle>
                        <CardDescription>
                            A role carries exactly this set. Saving replaces the previous set; it is
                            not additive.
                            {role?.name === 'SUPER_ADMIN'
                                ? ' SUPER_ADMIN must keep the access-management permissions; the server rejects a set that removes them.'
                                : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PermissionMatrix
                            permissions={catalog.permissions}
                            value={form.data.permissions}
                            onChange={(value) => {
                                form.setData('permissions', value);
                                clearError('permissions');
                            }}
                            disabled={form.processing}
                            error={errors.permissions}
                        />
                    </CardContent>
                </Card>

                <StickyFormBar
                    summary={summary}
                    isDirty={form.isDirty}
                    processing={form.processing}
                    submitLabel={role ? 'Save role' : 'Create role'}
                    cancelLabel="Cancel"
                    onCancel={() => router.visit(accessControlUrls.roles)}
                    idleLabel={role ? 'Role loaded' : 'New role'}
                    idleTone="neutral"
                    processingLabel={role ? 'Saving…' : 'Creating…'}
                />
            </form>

            <CrudDialog
                open={navigationGuard.pendingUrl !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        navigationGuard.cancelLeave();
                    }
                }}
                title="Discard changes?"
                description="This role has unsaved permission changes. Leaving now discards them."
                confirmLabel="Discard changes"
                cancelLabel="Keep editing"
                confirmVariant="destructive"
                onConfirm={navigationGuard.confirmLeave}
            >
                <p className="text-sm text-surface-700 dark:text-surface-200">
                    The unsaved permission set will be lost.
                </p>
            </CrudDialog>
        </>
    );
}
