/**
 * Users/Create — Super Admin onboarding form.
 *
 * The account is never created with a visible credential. The default path is
 * a signed invitation; the manual path stores a temporary password and forces a
 * change at first sign-in. Both are server-enforced — the toggle is UX only.
 */
import { Head, router } from '@inertiajs/react';
import { KeyRound, Mail, ShieldCheck } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import MultiSelectField from '@/Components/Forms/MultiSelectField';
import PasswordField from '@/Components/Forms/PasswordField';
import StickyFormBar from '@/Components/Forms/StickyFormBar';
import SwitchField from '@/Components/Forms/SwitchField';
import AppLayout from '@/Layouts/AppLayout';
import { useUnsavedChangesWarning, useZodForm } from '@/lib/forms';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH } from '@/lib/auth';
import {
    createUserSchema,
    toUserProfileForm,
    type CreateUserForm,
    type UpdateUserForm,
} from '@/Modules/Users/schemas/userProfile';
import { useUserAvailability } from '@/Modules/Users/hooks/useUserAvailability';
import UserProfileFields from './components/UserProfileFields';
import RoleGrantSummary from './components/RoleGrantSummary';

interface CreateUserPageProps {
    catalog: Modules.Users.Data.UserAccessCatalogData;
    googleMapsApiKey: string;
}

const initialValues = (): CreateUserForm => ({
    ...toUserProfileForm(),
    roles: [],
    permissions: [],
    send_invitation: true,
    temporary_password: '',
    temporary_password_confirmation: '',
});

export default function UsersCreate({ catalog, googleMapsApiKey }: CreateUserPageProps) {
    const { form, errors, submit, touch, clearError, setFields } = useZodForm({
        initial: initialValues(),
        schema: createUserSchema,
    });

    const emailAvailability = useUserAvailability('email', form.data.email);
    const usernameAvailability = useUserAvailability('username', form.data.username);

    const setProfile = <K extends keyof UpdateUserForm>(key: K, value: UpdateUserForm[K]): void => {
        /*
         * The merge lives in `useZodForm.setFields`. Inertia v3 replaces the
         * entire payload for an object `setData`, which collapsed the form to
         * one key and crashed the availability probe on the next render. The
         * cast is the union-key boundary only; the runtime write is a merge.
         */
        setFields({ [key]: value } as Partial<CreateUserForm>);
    };

    const blockingCheck =
        emailAvailability.state === 'checking' ||
        emailAvailability.state === 'unavailable' ||
        usernameAvailability.state === 'checking' ||
        usernameAvailability.state === 'unavailable';

    useUnsavedChangesWarning(form.isDirty);

    const roleCount = form.data.roles.length;
    const permissionCount = form.data.permissions.length;
    const summary = `${form.data.send_invitation ? 'Invitation' : 'Temporary password'} · ${
        form.data.email || 'no email yet'
    } · ${roleCount} ${roleCount === 1 ? 'role' : 'roles'} · ${permissionCount} ${
        permissionCount === 1 ? 'permission' : 'permissions'
    }`;

    const onSubmit = (event: React.FormEvent): void => {
        event.preventDefault();

        if (blockingCheck) {
            return;
        }

        submit(() => {
            form.post('/users', { preserveScroll: true });
        });
    };

    return (
        <AppLayout title="Create user">
            <Head title="Create user" />

            <form onSubmit={onSubmit} className="mx-auto flex max-w-4xl flex-col gap-6" noValidate>
                <FormErrorSummary errors={errors} />

                <Card>
                    <CardHeader>
                        <CardTitle>Identity and contact</CardTitle>
                        <CardDescription>
                            The invitation and every audit entry use this identity.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <UserProfileFields
                            data={form.data}
                            errors={errors}
                            onChange={setProfile}
                            onPatch={(patch) => setFields(patch)}
                            onBlur={touch}
                            availability={{
                                username: usernameAvailability,
                                email: emailAvailability,
                            }}
                            googleMapsApiKey={googleMapsApiKey}
                            disabled={form.processing}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-aq-accent" aria-hidden="true" />
                            Access
                        </CardTitle>
                        <CardDescription>
                            A role grants its permission set. Direct overrides are added on top of
                            the role, never instead of it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <MultiSelectField
                            label="Roles"
                            name="roles"
                            value={form.data.roles}
                            onChange={(value) => {
                                form.setData('roles', value);
                                clearError('roles');
                            }}
                            options={catalog.roles.map((role) => ({
                                value: role.name,
                                label: role.name,
                            }))}
                            error={errors.roles}
                            disabled={form.processing}
                            placeholder="No role"
                        />

                        <MultiSelectField
                            label="Direct permissions"
                            name="permissions"
                            value={form.data.permissions}
                            onChange={(value) => {
                                form.setData('permissions', value);
                                clearError('permissions');
                            }}
                            options={catalog.permissions.map((permission) => ({
                                value: permission,
                                label: permission,
                            }))}
                            error={errors.permissions}
                            disabled={form.processing}
                            placeholder="No overrides"
                        />

                        <div className="sm:col-span-2">
                            <RoleGrantSummary catalog={catalog} selectedRoles={form.data.roles} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {form.data.send_invitation ? (
                                <Mail className="size-4 text-aq-accent" aria-hidden="true" />
                            ) : (
                                <KeyRound className="size-4 text-aq-warning" aria-hidden="true" />
                            )}
                            Handover
                        </CardTitle>
                        <CardDescription>
                            Recommended: the user chooses their own password through a signed link
                            that expires in 48 hours.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <SwitchField
                            label="Send activation email"
                            name="send_invitation"
                            checked={form.data.send_invitation}
                            onChange={(checked) => {
                                form.setData('send_invitation', checked);
                                clearError('send_invitation');
                            }}
                            description="The account stays invited and cannot sign in until the link is used."
                            disabled={form.processing}
                        />

                        {!form.data.send_invitation ? (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <PasswordField
                                    label="Temporary password"
                                    name="temporary_password"
                                    value={form.data.temporary_password}
                                    onChange={(value) => {
                                        form.setData('temporary_password', value);
                                        clearError('temporary_password');
                                    }}
                                    onBlur={() => touch('temporary_password')}
                                    error={errors.temporary_password}
                                    hint={PASSWORD_HINT}
                                    maxLength={PASSWORD_MAX_LENGTH}
                                    autocomplete="new-password"
                                    required
                                />

                                <PasswordField
                                    label="Confirm temporary password"
                                    name="temporary_password_confirmation"
                                    value={form.data.temporary_password_confirmation}
                                    onChange={(value) => {
                                        form.setData('temporary_password_confirmation', value);
                                        clearError('temporary_password_confirmation');
                                    }}
                                    onBlur={() => touch('temporary_password_confirmation')}
                                    error={errors.temporary_password_confirmation}
                                    maxLength={PASSWORD_MAX_LENGTH}
                                    autocomplete="new-password"
                                    required
                                />
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                <StickyFormBar
                    summary={summary}
                    isDirty={form.isDirty}
                    processing={form.processing}
                    submitLabel="Create user"
                    cancelLabel="Cancel"
                    onCancel={() => router.visit('/users')}
                    idleLabel="New account"
                    idleTone="neutral"
                    processingLabel="Creating…"
                    disabled={blockingCheck}
                />
            </form>
        </AppLayout>
    );
}
