/**
 * Users/Edit — profile edit for an existing staff account.
 *
 * Status and access are separate operations on the Show screen: a profile save
 * must never silently change a credential state or a role set.
 */
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import StickyFormBar from '@/Components/Forms/StickyFormBar';
import AppLayout from '@/Layouts/AppLayout';
import { useUnsavedChangesWarning, useZodForm } from '@/lib/forms';
import {
    toUserProfileForm,
    updateUserSchema,
    type UpdateUserForm,
} from '@/Modules/Users/schemas/userProfile';
import { useUserAvailability } from '@/Modules/Users/hooks/useUserAvailability';
import UserProfileFields from './components/UserProfileFields';
import UserStatusBadge from './components/UserStatusBadge';

interface EditUserPageProps {
    user: Modules.Users.Data.ManagedUserData;
    googleMapsApiKey: string;
}

export default function UsersEdit({ user, googleMapsApiKey }: EditUserPageProps) {
    const { form, errors, submit, submitAttempts, touch, setField, setFields } = useZodForm({
        initial: toUserProfileForm(user),
        schema: updateUserSchema,
    });

    /*
     * Only probe a value the administrator actually changed. Probing on mount
     * made an untouched edit wait on two round-trips before it could be saved
     * and announced "Email is available." for a value nobody typed.
     */
    const emailChanged = form.data.email !== user.email;
    const usernameChanged = (form.data.username ?? '') !== (user.username ?? '');

    const emailAvailability = useUserAvailability(
        'email',
        form.data.email,
        user.uuid,
        emailChanged,
    );
    const usernameAvailability = useUserAvailability(
        'username',
        form.data.username,
        user.uuid,
        usernameChanged,
    );

    const blockingCheck =
        emailAvailability.state === 'checking' ||
        emailAvailability.state === 'unavailable' ||
        usernameAvailability.state === 'checking' ||
        usernameAvailability.state === 'unavailable';

    useUnsavedChangesWarning(form.isDirty);

    const summary = `Editing ${user.name} · ${user.email}`;

    const setProfile = <K extends keyof UpdateUserForm>(key: K, value: UpdateUserForm[K]): void => {
        setField(key, value);
    };

    return (
        <AppLayout title="Edit user">
            <Head title={`Edit ${user.name}`} />

            {/* No `mx-auto`: same left-edge alignment as the "Edit user" title. */}
            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (blockingCheck) {
                        return;
                    }

                    submit(() => {
                        form.put(`/users/${user.uuid}`, { preserveScroll: true });
                    });
                }}
                className="flex max-w-4xl flex-col gap-6"
                noValidate
            >
                <div className="flex flex-wrap items-center gap-3">
                    <p className="text-sm text-surface-600 dark:text-surface-300">
                        {user.name} · {user.email}
                    </p>
                    <UserStatusBadge status={user.status} label={user.status_label} />
                </div>

                <FormErrorSummary errors={errors} submitAttempts={submitAttempts} />

                <Card>
                    <CardHeader>
                        <CardTitle>Identity and contact</CardTitle>
                        <CardDescription>
                            Changing the e-mail address requires the owner to prove it again.
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

                <StickyFormBar
                    summary={summary}
                    isDirty={form.isDirty}
                    processing={form.processing}
                    submitLabel="Save changes"
                    cancelLabel="Cancel"
                    onCancel={() => router.visit(`/users/${user.uuid}`)}
                    onDiscard={() => form.reset()}
                    disabled={blockingCheck}
                />
            </form>
        </AppLayout>
    );
}
