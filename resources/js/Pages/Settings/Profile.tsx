/**
 * Settings/Profile — the account screen.
 *
 * Four tabs over one page: identity, address, password and two-factor.
 * Profile/address/password writes stay on Fortify's routes; the 2FA panel owns
 * its own client-side reads through a module hook. The page renders inside
 * `AppLayout` so the workspace shell (rail, header, command palette) is visible
 * here instead of a bare form on an empty background.
 */
import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    CheckCircle2,
    KeyRound,
    MailWarning,
    MapPin,
    ScanFace,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/Components/ui/tabs';
import { Tag } from '@/Components/ui/tag';
import AvatarCropperField from '@/Components/Forms/AvatarCropperField';
import DateField from '@/Components/Forms/DateField';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import PhoneField from '@/Components/Forms/PhoneField';
import SelectField from '@/Components/Forms/SelectField';
import TextField from '@/Components/Forms/TextField';
import { useZodForm } from '@/lib/forms';
import { statusMessage } from '@/lib/auth';
import { useProfilePhotoUpload } from '@/Modules/Auth/hooks/useProfilePhotoUpload';
import { usePasskeys } from '@/Modules/Auth/hooks/usePasskeys';
import {
    profileAddressSchema,
    profileDetailsSchema,
    toProfileAddressForm,
    toProfileDetailsForm,
    updatePasswordSchema,
} from '@/Modules/Users/schemas/profileSettings';
import AddressSection from './components/AddressSection';
import PasskeySection from './components/PasskeySection';
import PasswordSection from './components/PasswordSection';
import SecuritySection from './components/SecuritySection';
import SettingsCard from './components/SettingsCard';

interface SettingsProfileProps extends Record<string, unknown> {
    user: App.Data.Auth.UserData;
    googleMapsApiKey: string;
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
    passkeys: App.Data.Auth.PasskeyData[];
    passkeysCount: number;
    status: string | null;
}

type TabValue = 'profile' | 'address' | 'password' | 'security';

const TAB_VALUES: readonly TabValue[] = ['profile', 'address', 'password', 'security'];

const isTabValue = (value: string): value is TabValue =>
    TAB_VALUES.some((candidate) => candidate === value);

const tabFromHash = (): TabValue => {
    if (typeof window === 'undefined') {
        return 'profile';
    }

    const raw = window.location.hash.replace('#', '');
    const candidate = raw === 'two-factor' ? 'security' : raw;

    return isTabValue(candidate) ? candidate : 'profile';
};

const todayIso = (): string => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

const initialsOf = (name: string): string =>
    name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'AS';

const focusField = (field: string): void => {
    document.querySelector<HTMLElement>(`[name="${field}"]`)?.focus();
};

export default function Profile() {
    const page = usePage<SettingsProfileProps>();
    const user = page.props.user;
    const [tab, setTab] = useState<TabValue>(tabFromHash);

    const detailsForm = useZodForm({
        initial: toProfileDetailsForm(user),
        schema: profileDetailsSchema,
    });

    const addressForm = useZodForm({
        initial: toProfileAddressForm(user),
        schema: profileAddressSchema,
    });

    const passwordForm = useZodForm({
        initial: {
            current_password: '',
            password: '',
            password_confirmation: '',
        },
        schema: updatePasswordSchema,
    });

    const [photo, setPhoto] = useState<File | null>(null);
    const { uploading, error: photoError, upload, remove } = useProfilePhotoUpload();
    const passkeys = usePasskeys();

    useEffect(() => {
        const syncFromHash = (): void => setTab(tabFromHash());
        window.addEventListener('hashchange', syncFromHash);

        return () => window.removeEventListener('hashchange', syncFromHash);
    }, []);

    const changeTab = (next: unknown): void => {
        if (typeof next !== 'string' || !isTabValue(next)) {
            return;
        }

        setTab(next);
        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}${window.location.search}#${next}`,
        );
    };

    const onPhotoChange = (file: File | null): void => {
        setPhoto(file);

        if (file !== null) {
            void upload(file);
        } else {
            void remove();
        }
    };

    const onSubmitDetails = (event: React.FormEvent): void => {
        event.preventDefault();

        detailsForm.submit(() => {
            detailsForm.form.put('/user/profile-information', {
                preserveScroll: true,
            });
        });
    };

    const onSubmitAddress = (event: React.FormEvent): void => {
        event.preventDefault();

        addressForm.submit(() => {
            /*
             * Fortify's updater always validates the identity fields, so the
             * address write carries the current server identity unchanged.
             * Sending the draft identity would make "Save address" silently
             * save unsaved name/email edits too.
             */
            addressForm.form.transform((data) => ({
                first_name: user.first_name,
                last_name: user.last_name ?? '',
                username: user.username ?? '',
                email: user.email,
                phone: user.phone ?? '',
                date_of_birth: user.date_of_birth ?? '',
                gender: user.gender,
                ...data,
            }));

            addressForm.form.put('/user/profile-information', {
                preserveScroll: true,
            });
        });
    };

    const onSubmitPassword = (event: React.FormEvent): void => {
        event.preventDefault();

        passwordForm.submit(() => {
            passwordForm.form.put('/user/password', {
                preserveScroll: true,
                onSuccess: () =>
                    passwordForm.form.reset(
                        'current_password',
                        'password',
                        'password_confirmation',
                    ),
            });
        });
    };

    const flash = statusMessage(page.props.status);
    const hasAddressCoordinates =
        addressForm.form.data.latitude !== null && addressForm.form.data.longitude !== null;

    const completenessParts = [
        user.first_name,
        user.last_name,
        user.username,
        user.phone,
        user.date_of_birth,
        user.address,
        user.city,
        user.state,
        user.zip_code,
        user.country,
        user.profile_photo_url,
    ];

    const completed = completenessParts.filter(
        (part) => typeof part === 'string' && part.trim() !== '',
    ).length;
    const completeness = Math.round((completed / completenessParts.length) * 100);

    return (
        <AppLayout title="Settings">
            <Head title="Settings — AquaShield" />

            <div className="mx-auto w-full max-w-5xl space-y-6">
                <section className="relative overflow-hidden rounded-2xl border border-aq-shell-line bg-gradient-to-br from-surface-0 via-surface-0 to-aq-accent/5 p-5 shadow-sm sm:p-6 dark:from-surface-900 dark:via-surface-900 dark:to-aq-accent/10">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <Avatar className="size-16 border border-aq-shell-line shadow-sm">
                            <AvatarImage
                                src={user.profile_photo_url ?? undefined}
                                alt={user.name}
                            />
                            <AvatarFallback className="bg-aq-accent/15 text-lg font-semibold text-aq-accent">
                                {initialsOf(user.name)}
                            </AvatarFallback>
                        </Avatar>

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="font-heading text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                                    {user.name}
                                </h2>

                                {user.email_verified ? (
                                    <Tag variant="success">
                                        <BadgeCheck className="mr-1 size-3" aria-hidden="true" />
                                        Email verified
                                    </Tag>
                                ) : (
                                    <Tag variant="warning">
                                        <MailWarning className="mr-1 size-3" aria-hidden="true" />
                                        Email unverified
                                    </Tag>
                                )}

                                <Tag variant={page.props.passkeysCount > 0 ? 'success' : 'outline'}>
                                    <ScanFace className="mr-1 size-3" aria-hidden="true" />
                                    {page.props.passkeysCount > 0
                                        ? `Passkey ×${page.props.passkeysCount}`
                                        : 'No passkey'}
                                </Tag>

                                <Tag variant={user.two_factor_enabled ? 'success' : 'outline'}>
                                    <ShieldCheck className="mr-1 size-3" aria-hidden="true" />
                                    {user.two_factor_enabled ? 'TOTP on' : 'TOTP off'}
                                </Tag>
                            </div>

                            <p className="mt-1 truncate text-sm text-surface-600 dark:text-surface-300">
                                {user.email}
                                {user.username !== null && (
                                    <span className="ml-2 text-surface-500 dark:text-surface-400">
                                        @{user.username}
                                    </span>
                                )}
                            </p>

                            <div className="mt-4 max-w-md">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="font-medium text-surface-700 dark:text-surface-200">
                                        Profile completeness
                                    </span>
                                    <span className="tabular-nums text-surface-600 dark:text-surface-300">
                                        {completeness}%
                                    </span>
                                </div>

                                <div
                                    className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                    role="progressbar"
                                    aria-label="Profile completeness"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={completeness}
                                >
                                    <div
                                        className="h-full rounded-full bg-aq-accent transition-[width] duration-300 motion-reduce:transition-none"
                                        style={{ width: `${completeness}%` }}
                                    />
                                </div>

                                <p className="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                                    A complete profile makes it easier for the team to reach you.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {flash !== null && (
                    <p
                        role="status"
                        className="flex items-start gap-2 rounded-lg border border-aq-success/30 bg-aq-success/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                    >
                        <CheckCircle2
                            className="mt-0.5 size-4 shrink-0 text-aq-success"
                            aria-hidden="true"
                        />
                        {flash}
                    </p>
                )}

                {!user.email_verified && (
                    <p
                        role="alert"
                        className="flex items-start gap-2 rounded-lg border border-aq-warning/40 bg-aq-warning/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                    >
                        <MailWarning className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        Your email address is not verified. Open the verification link sent to your
                        inbox before signing in from a new device.
                    </p>
                )}

                <Tabs value={tab} onValueChange={changeTab}>
                    <TabsList
                        aria-label="Settings sections"
                        className="rounded-xl border border-aq-shell-line bg-surface-0 px-1 dark:bg-surface-900"
                    >
                        <TabsTab value="profile">
                            <UserRound className="size-3.5" aria-hidden="true" />
                            Profile
                        </TabsTab>

                        <TabsTab value="address">
                            <MapPin className="size-3.5" aria-hidden="true" />
                            Address
                        </TabsTab>

                        <TabsTab value="password">
                            <KeyRound className="size-3.5" aria-hidden="true" />
                            Password
                        </TabsTab>

                        <TabsTab value="security">
                            <ShieldCheck className="size-3.5" aria-hidden="true" />
                            Security
                        </TabsTab>
                    </TabsList>

                    <TabsPanel value="profile" padded={false} keepMounted className="pt-6">
                        <div className="grid gap-6">
                            <SettingsCard
                                title="Profile photo"
                                description="A clear headshot helps the team recognise you in the workspace."
                                icon={<UserRound className="size-4" aria-hidden="true" />}
                            >
                                <AvatarCropperField
                                    label="Profile photo"
                                    name="photo"
                                    value={photo}
                                    onChange={onPhotoChange}
                                    currentUrl={user.profile_photo_url}
                                    fallback={initialsOf(user.name)}
                                    error={photoError ?? undefined}
                                    hint="PNG, JPEG or WebP. The image is cropped to a square before upload."
                                    disabled={uploading}
                                />
                            </SettingsCard>

                            <SettingsCard
                                title="Personal information"
                                description="How your name, contact details and sign-in identity appear in AquaShield."
                                icon={<UserRound className="size-4" aria-hidden="true" />}
                                action={
                                    <Tag variant={user.email_verified ? 'success' : 'warning'}>
                                        {user.email_verified ? 'Verified email' : 'Verify email'}
                                    </Tag>
                                }
                            >
                                <form
                                    onSubmit={onSubmitDetails}
                                    className="flex flex-col gap-4"
                                    noValidate
                                >
                                    <FormErrorSummary
                                        errors={detailsForm.errors}
                                        labels={{
                                            first_name: 'First name',
                                            last_name: 'Last name',
                                            username: 'Username',
                                            email: 'Email',
                                            phone: 'Phone',
                                            date_of_birth: 'Date of birth',
                                            gender: 'Gender',
                                        }}
                                        onFieldFocus={focusField}
                                    />

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <TextField
                                            label="First name"
                                            name="first_name"
                                            value={detailsForm.form.data.first_name}
                                            onChange={(value) =>
                                                detailsForm.form.setData('first_name', value)
                                            }
                                            onBlur={() => detailsForm.touch('first_name')}
                                            error={detailsForm.errors.first_name}
                                            autocomplete="given-name"
                                            required
                                        />

                                        <TextField
                                            label="Last name"
                                            name="last_name"
                                            value={detailsForm.form.data.last_name}
                                            onChange={(value) =>
                                                detailsForm.form.setData('last_name', value)
                                            }
                                            onBlur={() => detailsForm.touch('last_name')}
                                            error={detailsForm.errors.last_name}
                                            autocomplete="family-name"
                                        />

                                        <TextField
                                            label="Username"
                                            name="username"
                                            value={detailsForm.form.data.username}
                                            onChange={(value) =>
                                                detailsForm.form.setData('username', value)
                                            }
                                            onBlur={() => detailsForm.touch('username')}
                                            error={detailsForm.errors.username}
                                            autocomplete="username"
                                            hint="Lowercase letters, numbers and underscores."
                                        />

                                        <TextField
                                            label="Email address"
                                            name="email"
                                            type="email"
                                            value={detailsForm.form.data.email}
                                            onChange={(value) =>
                                                detailsForm.form.setData('email', value)
                                            }
                                            onBlur={() => detailsForm.touch('email')}
                                            error={detailsForm.errors.email}
                                            autocomplete="email"
                                            required
                                        />

                                        <PhoneField
                                            label="Phone"
                                            name="phone"
                                            value={detailsForm.form.data.phone}
                                            onChange={(value) =>
                                                detailsForm.form.setData('phone', value)
                                            }
                                            onBlur={() => detailsForm.touch('phone')}
                                            error={detailsForm.errors.phone}
                                            hint="Pick the country, then type the number. It is stored in E.164."
                                        />

                                        <DateField
                                            label="Date of birth"
                                            name="date_of_birth"
                                            value={detailsForm.form.data.date_of_birth}
                                            onChange={(value) =>
                                                detailsForm.form.setData(
                                                    'date_of_birth',
                                                    value ?? '',
                                                )
                                            }
                                            onBlur={() => detailsForm.touch('date_of_birth')}
                                            error={detailsForm.errors.date_of_birth}
                                            max={todayIso()}
                                        />

                                        <SelectField
                                            label="Gender"
                                            name="gender"
                                            value={detailsForm.form.data.gender}
                                            onChange={(value) =>
                                                detailsForm.form.setData(
                                                    'gender',
                                                    value === 'male' ||
                                                        value === 'female' ||
                                                        value === 'other'
                                                        ? value
                                                        : null,
                                                )
                                            }
                                            onBlur={() => detailsForm.touch('gender')}
                                            error={detailsForm.errors.gender}
                                            placeholder="Not specified"
                                            options={[
                                                { label: 'Male', value: 'male' },
                                                { label: 'Female', value: 'female' },
                                                { label: 'Other', value: 'other' },
                                            ]}
                                        />
                                    </div>

                                    <FormActions
                                        submitLabel="Save profile"
                                        loading={detailsForm.form.processing}
                                        disabled={detailsForm.form.processing}
                                        className="pt-1"
                                    />
                                </form>
                            </SettingsCard>
                        </div>
                    </TabsPanel>

                    <TabsPanel value="address" padded={false} keepMounted className="pt-6">
                        <SettingsCard
                            title="Address"
                            description="A verified US address with coordinates helps routing, scheduling and territory assignment."
                            icon={<MapPin className="size-4" aria-hidden="true" />}
                            action={
                                <Tag variant={hasAddressCoordinates ? 'success' : 'outline'}>
                                    {hasAddressCoordinates ? 'Google verified' : 'Manual'}
                                </Tag>
                            }
                        >
                            <form
                                onSubmit={onSubmitAddress}
                                className="flex flex-col gap-5"
                                noValidate
                            >
                                <FormErrorSummary
                                    errors={addressForm.errors}
                                    labels={{
                                        address: 'Street address',
                                        address_2: 'Address line 2',
                                        zip_code: 'ZIP code',
                                        city: 'City',
                                        state: 'State',
                                        country: 'Country',
                                    }}
                                    onFieldFocus={focusField}
                                />

                                <AddressSection
                                    apiKey={page.props.googleMapsApiKey}
                                    form={addressForm.form}
                                    errors={addressForm.errors}
                                    onBlur={(field) => addressForm.touch(field)}
                                />

                                <FormActions
                                    submitLabel="Save address"
                                    loading={addressForm.form.processing}
                                    disabled={addressForm.form.processing}
                                />
                            </form>
                        </SettingsCard>
                    </TabsPanel>

                    <TabsPanel value="password" padded={false} keepMounted className="pt-6">
                        <SettingsCard
                            title="Password"
                            description="Changing your password signs out other browsers on this account."
                            icon={<KeyRound className="size-4" aria-hidden="true" />}
                        >
                            <form
                                onSubmit={onSubmitPassword}
                                className="flex flex-col gap-5"
                                noValidate
                            >
                                <PasswordSection
                                    form={passwordForm.form}
                                    errors={passwordForm.errors}
                                    onBlur={(field) => passwordForm.touch(field)}
                                />

                                <FormActions
                                    submitLabel="Update password"
                                    loading={passwordForm.form.processing}
                                    disabled={passwordForm.form.processing}
                                />
                            </form>
                        </SettingsCard>
                    </TabsPanel>

                    <TabsPanel value="security" padded={false} keepMounted className="pt-6">
                        <div className="flex flex-col gap-6">
                            <PasskeySection
                                passkeys={page.props.passkeys}
                                supported={passkeys.supported}
                                adding={passkeys.adding}
                                removingId={passkeys.removingId}
                                onAdd={passkeys.add}
                                onRemove={passkeys.remove}
                                onConfirmPassword={passkeys.confirmPassword}
                                onConfirmWithPasskey={passkeys.confirmWithPasskey}
                            />

                            <SecuritySection
                                enabled={page.props.twoFactorEnabled}
                                confirmed={page.props.twoFactorConfirmed}
                                passkeysCount={page.props.passkeysCount}
                                onConfirmWithPasskey={
                                    page.props.passkeysCount > 0
                                        ? passkeys.confirmWithPasskey
                                        : undefined
                                }
                            />
                        </div>
                    </TabsPanel>
                </Tabs>
            </div>
        </AppLayout>
    );
}
