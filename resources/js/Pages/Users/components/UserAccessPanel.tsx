/**
 * Role and direct-permission assignment for one user.
 *
 * Two forms, two endpoints, two audit events. The role form is a sync (replace
 * the role set); the permission form only manages direct overrides and leaves
 * everything the roles grant untouched.
 */
import { useZodForm } from '@/lib/forms';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import MultiSelectField from '@/Components/Forms/MultiSelectField';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { userPermissionsSchema, userRolesSchema } from '@/Modules/Users/schemas/userAccess';
import RoleGrantSummary from './RoleGrantSummary';

interface UserAccessPanelProps {
    user: Modules.Users.Data.ManagedUserData;
    catalog: Modules.Users.Data.UserAccessCatalogData;
    canAssignRoles: boolean;
    canAssignPermissions: boolean;
}

const focusField = (field: string): void => {
    document.querySelector<HTMLElement>(`[name="${field}"]`)?.focus();
};

export default function UserAccessPanel({
    user,
    catalog,
    canAssignRoles,
    canAssignPermissions,
}: UserAccessPanelProps) {
    const rolesForm = useZodForm({
        initial: { roles: user.roles },
        schema: userRolesSchema,
    });

    const permissionsForm = useZodForm({
        initial: { permissions: user.direct_permissions },
        schema: userPermissionsSchema,
    });

    if (!canAssignRoles && !canAssignPermissions) {
        return null;
    }

    return (
        <div className="grid gap-6">
            {canAssignRoles ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                        <CardDescription>
                            Replaces the role set. A role grants its permission list; removing a
                            role removes those grants.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                rolesForm.submit(() => {
                                    rolesForm.form.put(`/users/${user.uuid}/roles`, {
                                        preserveScroll: true,
                                    });
                                });
                            }}
                            className="flex flex-col gap-4"
                            noValidate
                        >
                            <FormErrorSummary errors={rolesForm.errors} onFieldFocus={focusField} />

                            <MultiSelectField
                                label="Assigned roles"
                                name="roles"
                                value={rolesForm.form.data.roles}
                                onChange={(value) => {
                                    rolesForm.form.setData('roles', value);
                                    rolesForm.clearError('roles');
                                }}
                                options={catalog.roles.map((role) => ({
                                    value: role.name,
                                    label: role.name,
                                }))}
                                error={rolesForm.errors.roles}
                                hint="A user can hold more than one role; the union of their permissions applies."
                                disabled={rolesForm.form.processing}
                            />

                            <RoleGrantSummary
                                catalog={catalog}
                                selectedRoles={rolesForm.form.data.roles}
                            />

                            <FormActions
                                submitLabel="Save roles"
                                loading={rolesForm.form.processing}
                                disabled={rolesForm.form.processing}
                            />
                        </form>
                    </CardContent>
                </Card>
            ) : null}

            {canAssignPermissions ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Direct permissions</CardTitle>
                        <CardDescription>
                            Added on top of the roles. Removing a direct permission leaves the
                            role-derived access intact.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                permissionsForm.submit(() => {
                                    permissionsForm.form.put(`/users/${user.uuid}/permissions`, {
                                        preserveScroll: true,
                                    });
                                });
                            }}
                            className="flex flex-col gap-4"
                            noValidate
                        >
                            <FormErrorSummary
                                errors={permissionsForm.errors}
                                onFieldFocus={focusField}
                            />

                            <MultiSelectField
                                label="Overrides"
                                name="permissions"
                                value={permissionsForm.form.data.permissions}
                                onChange={(value) => {
                                    permissionsForm.form.setData('permissions', value);
                                    permissionsForm.clearError('permissions');
                                }}
                                options={catalog.permissions.map((permission) => ({
                                    value: permission,
                                    label: permission,
                                }))}
                                error={permissionsForm.errors.permissions}
                                searchPlaceholder="Search permissions…"
                                disabled={permissionsForm.form.processing}
                            />

                            <FormActions
                                submitLabel="Save direct permissions"
                                loading={permissionsForm.form.processing}
                                disabled={permissionsForm.form.processing}
                            />
                        </form>
                    </CardContent>
                </Card>
            ) : null}
        </div>
    );
}
