<script setup lang="ts">
/**
 * Settings/Profile — the account screen.
 *
 * Four tabs over one page: identity, address, password and two-factor. The
 * profile and address writes stay on Fortify's routes; the 2FA panel owns its
 * client-side reads through a module composable. The page renders inside
 * `AppLayout`, so the workspace shell stays visible instead of dropping the
 * user onto a bare form.
 */
import { computed, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AppButton,
    AvatarCropField,
    DateField,
    FormErrorSummary,
    PhoneField,
    SelectField,
    TextField,
} from '@/Components/Forms';
import AppLayout from '@/Layouts/AppLayout.vue';
import { statusMessage } from '@/lib/auth';
import { useZodForm } from '@/lib/forms';
import { USERNAME_HINT, USERNAME_MAX } from '@/lib/username';
import { useProfilePhotoUpload } from '@/Modules/Auth/composables/useProfilePhotoUpload';
import {
    identityPayload,
    profileAddressSchema,
    profileDetailsSchema,
    updatePasswordSchema,
    toProfileAddressForm,
    toProfileDetailsForm,
} from '@/Modules/Users/schemas/profileSettings';
import Avatar from '@/volt/Avatar.vue';
import Tab from '@/volt/Tab.vue';
import TabList from '@/volt/TabList.vue';
import TabPanel from '@/volt/TabPanel.vue';
import TabPanels from '@/volt/TabPanels.vue';
import Tabs from '@/volt/Tabs.vue';
import Tag from '@/volt/Tag.vue';
import AddressSection from './components/AddressSection.vue';
import PasswordSection from './components/PasswordSection.vue';
import SecuritySection from './components/SecuritySection.vue';
import SettingsCard from './components/SettingsCard.vue';

defineOptions({
    layout: [AppLayout, { title: 'Settings' }],
});

const props = defineProps<{
    user: App.Data.Auth.UserData;
    googleMapsApiKey: string;
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
    status?: string | null;
}>();

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

const today = new Date();
const tab = ref<TabValue>(tabFromHash());

const initials = computed<string>(() => {
    const parts = props.user.name.trim().split(/\s+/).filter(Boolean);

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'AS'
    );
});

const flashMessage = computed<string | null>(() => statusMessage(props.status ?? null));

const twoFactorEnabled = ref<boolean>(props.twoFactorEnabled);
const twoFactorConfirmed = ref<boolean>(props.twoFactorConfirmed);

watch(
    () => props.twoFactorEnabled,
    (value) => {
        twoFactorEnabled.value = value;
    },
);

watch(
    () => props.twoFactorConfirmed,
    (value) => {
        twoFactorConfirmed.value = value;
    },
);

const detailsFormEl = useTemplateRef<HTMLFormElement>('detailsFormEl');
const addressFormEl = useTemplateRef<HTMLFormElement>('addressFormEl');
const passwordFormEl = useTemplateRef<HTMLFormElement>('passwordFormEl');

const {
    form: detailsForm,
    errors: detailsErrors,
    submit: submitDetails,
    touch: touchDetails,
    clearError: clearDetailsError,
} = useZodForm({
    initial: toProfileDetailsForm(props.user),
    schema: profileDetailsSchema,
    formEl: detailsFormEl,
});

const {
    form: addressForm,
    errors: addressErrors,
    submit: submitAddress,
    touch: touchAddress,
} = useZodForm({
    initial: toProfileAddressForm(props.user),
    schema: profileAddressSchema,
    formEl: addressFormEl,
});

const {
    form: passwordForm,
    errors: passwordErrors,
    submit: submitPassword,
    touch: touchPassword,
    clearError: clearPasswordError,
} = useZodForm({
    initial: {
        current_password: '',
        password: '',
        password_confirmation: '',
    },
    schema: updatePasswordSchema,
    formEl: passwordFormEl,
});

const photoFile = ref<File | null>(null);
const resendingVerification = ref<boolean>(false);

const resendVerification = (): void => {
    resendingVerification.value = true;

    router.post(
        '/email/verification-notification',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                resendingVerification.value = false;
            },
        },
    );
};
const {
    uploading: photoUploading,
    error: photoError,
    upload: uploadPhoto,
    remove: removePhoto,
} = useProfilePhotoUpload();

const onPhotoChange = (file: File | null): void => {
    photoFile.value = file;

    if (file !== null) {
        void uploadPhoto(file);

        return;
    }

    if (props.user.profile_photo_url !== null) {
        void removePhoto();
    }
};

const addressHasCoordinates = computed<boolean>(
    () => addressForm.latitude !== null && addressForm.longitude !== null,
);

const completeness = computed<number>(() => {
    const parts: Array<string | null> = [
        props.user.first_name,
        props.user.last_name,
        props.user.username,
        props.user.phone,
        props.user.date_of_birth,
        props.user.address,
        props.user.city,
        props.user.state,
        props.user.zip_code,
        props.user.country,
        props.user.profile_photo_url,
    ];

    const completed = parts.filter((part) => typeof part === 'string' && part.trim() !== '').length;

    return Math.round((completed / parts.length) * 100);
});

const changeTab = (value: string | number): void => {
    if (typeof value === 'string' && isTabValue(value)) {
        tab.value = value;
    }
};

const syncTabFromHash = (): void => {
    tab.value = tabFromHash();
};

const onUsernameInput = (): void => {
    detailsForm.username = detailsForm.username.toLowerCase();
    clearDetailsError('username');
};

const onDetailsSubmit = (): void => {
    submitDetails(() =>
        detailsForm.put('/user/profile-information', {
            errorBag: 'updateProfileInformation',
            preserveScroll: true,
        }),
    );
};

const onAddressSubmit = (): void => {
    submitAddress(() => {
        /*
         * Fortify's updater always validates the identity fields, so the
         * address write carries the stored identity unchanged. Sending the
         * visible identity draft would make "Save address" silently save
         * unsaved name or email edits from the other tab.
         */
        addressForm.transform((data) => ({
            ...identityPayload(props.user),
            ...data,
        }));

        addressForm.put('/user/profile-information', {
            errorBag: 'updateProfileInformation',
            preserveScroll: true,
        });
    });
};

const onPasswordSubmit = (): void => {
    submitPassword(() =>
        passwordForm.put('/user/password', {
            errorBag: 'updatePassword',
            preserveScroll: true,
            onSuccess: () =>
                passwordForm.reset('current_password', 'password', 'password_confirmation'),
        }),
    );
};

watch(tab, (value) => {
    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${window.location.search}#${value}`,
    );
});

onMounted(() => window.addEventListener('hashchange', syncTabFromHash));
onUnmounted(() => window.removeEventListener('hashchange', syncTabFromHash));
</script>

<template>
    <Head title="Settings — AquaShield" />

    <div class="mx-auto w-full max-w-5xl space-y-6">
        <section
            class="relative overflow-hidden rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:p-6 dark:bg-surface-900"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <Avatar
                    v-if="props.user.profile_photo_url"
                    :image="props.user.profile_photo_url"
                    shape="circle"
                    size="xlarge"
                    class="border border-aq-shell-line shadow-sm"
                />
                <Avatar
                    v-else
                    :label="initials"
                    shape="circle"
                    size="xlarge"
                    class="border border-aq-shell-line bg-aq-accent/15 font-semibold text-aq-accent shadow-sm"
                />

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            class="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0"
                        >
                            {{ props.user.name }}
                        </h2>

                        <Tag :severity="props.user.email_verified ? 'success' : 'warn'">
                            <i
                                class="pi mr-1 text-[0.65rem]"
                                :class="
                                    props.user.email_verified
                                        ? 'pi-check-circle'
                                        : 'pi-exclamation-triangle'
                                "
                                aria-hidden="true"
                            />
                            {{ props.user.email_verified ? 'Email verified' : 'Email unverified' }}
                        </Tag>

                        <Tag
                            :severity="
                                twoFactorConfirmed
                                    ? 'success'
                                    : twoFactorEnabled
                                      ? 'warn'
                                      : 'secondary'
                            "
                        >
                            <i
                                class="pi mr-1 text-[0.65rem]"
                                :class="twoFactorConfirmed ? 'pi-shield' : 'pi-lock'"
                                aria-hidden="true"
                            />
                            {{
                                twoFactorConfirmed
                                    ? '2FA on'
                                    : twoFactorEnabled
                                      ? '2FA setup'
                                      : '2FA off'
                            }}
                        </Tag>
                    </div>

                    <p class="mt-1 truncate text-sm text-surface-600 dark:text-surface-300">
                        {{ props.user.email }}
                        <span
                            v-if="props.user.username"
                            class="ms-2 text-surface-500 dark:text-surface-400"
                        >
                            @{{ props.user.username }}
                        </span>
                    </p>

                    <div class="mt-4 max-w-md">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-surface-700 dark:text-surface-200">
                                Profile completeness
                            </span>
                            <span class="tabular-nums text-surface-600 dark:text-surface-300">
                                {{ completeness }}%
                            </span>
                        </div>

                        <div
                            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                            role="progressbar"
                            aria-label="Profile completeness"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuenow="completeness"
                        >
                            <div
                                class="h-full rounded-full bg-aq-accent transition-[width] duration-300 motion-reduce:transition-none"
                                :style="{ width: `${completeness}%` }"
                            />
                        </div>

                        <p class="mt-1.5 text-xs text-surface-500 dark:text-surface-400">
                            A complete profile makes it easier for the team to reach you.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <p
            v-if="flashMessage"
            role="status"
            class="flex items-start gap-2 rounded-lg border border-aq-success/30 bg-aq-success/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
        >
            <i class="pi pi-check-circle mt-0.5 shrink-0 text-aq-success" aria-hidden="true" />
            {{ flashMessage }}
        </p>

        <p
            v-if="!props.user.email_verified"
            role="alert"
            class="flex flex-wrap items-start gap-2 rounded-lg border border-aq-warning/40 bg-aq-warning/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
        >
            <i
                class="pi pi-exclamation-triangle mt-0.5 shrink-0 text-aq-warning"
                aria-hidden="true"
            />
            <span class="flex-1">
                Your email address is not verified. Open the verification link sent to your inbox
                before signing in from a new device. Until then, the dashboard keeps redirecting to
                the verification screen.
            </span>

            <AppButton
                type="button"
                :loading="resendingVerification"
                :disabled="resendingVerification"
                @click="resendVerification"
            >
                Resend verification email
            </AppButton>
        </p>

        <Tabs :value="tab" @update:value="changeTab">
            <TabList
                aria-label="Settings sections"
                class="rounded-xl border border-aq-shell-line bg-surface-0 px-1 dark:bg-surface-900"
            >
                <Tab value="profile">
                    <i class="pi pi-user me-2 text-xs" aria-hidden="true" />
                    Profile
                </Tab>
                <Tab value="address">
                    <i class="pi pi-map-marker me-2 text-xs" aria-hidden="true" />
                    Address
                </Tab>
                <Tab value="password">
                    <i class="pi pi-key me-2 text-xs" aria-hidden="true" />
                    Password
                </Tab>
                <Tab value="security">
                    <i class="pi pi-shield me-2 text-xs" aria-hidden="true" />
                    Security
                </Tab>
            </TabList>

            <!-- The strip is a control, the panels are content: a full step of
                 the page's own rhythm (24px, the same `space-y-6` that spaces
                 the sections above) separates them, so the panel reads as
                 belonging to the page instead of as the bottom half of the
                 tab strip. The cards sit on the page surface, the way the
                 rail's groups do. -->
            <TabPanels class="mt-6">
                <TabPanel value="profile">
                    <div class="grid gap-6">
                        <SettingsCard
                            title="Profile photo"
                            description="A clear headshot helps the team recognise you in the workspace."
                            icon="pi pi-user"
                        >
                            <AvatarCropField
                                v-model="photoFile"
                                label="Profile photo"
                                :current-url="props.user.profile_photo_url"
                                :max-size-mb="2"
                                hint="PNG, JPG or WebP, up to 2 MB. Crop and zoom before saving; stored on Cloudflare R2."
                                :error="photoError ?? undefined"
                                :disabled="photoUploading"
                                @update:model-value="onPhotoChange"
                            />
                        </SettingsCard>

                        <SettingsCard
                            title="Personal information"
                            description="How your name, contact details and sign-in identity appear in AquaShield."
                            icon="pi pi-id-card"
                        >
                            <template #action>
                                <Tag :severity="props.user.email_verified ? 'success' : 'warn'">
                                    {{
                                        props.user.email_verified
                                            ? 'Verified email'
                                            : 'Verify email'
                                    }}
                                </Tag>
                            </template>

                            <form
                                ref="detailsFormEl"
                                class="flex flex-col gap-4"
                                novalidate
                                @submit.prevent="onDetailsSubmit"
                            >
                                <FormErrorSummary
                                    :errors="detailsErrors"
                                    :form-el="detailsFormEl"
                                />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <TextField
                                        v-model="detailsForm.first_name"
                                        name="first_name"
                                        label="First name"
                                        autocomplete="given-name"
                                        required
                                        :error="detailsErrors.first_name"
                                        @update:model-value="clearDetailsError('first_name')"
                                        @blur="touchDetails('first_name')"
                                    />

                                    <TextField
                                        v-model="detailsForm.last_name"
                                        name="last_name"
                                        label="Last name"
                                        autocomplete="family-name"
                                        :error="detailsErrors.last_name"
                                        @update:model-value="clearDetailsError('last_name')"
                                        @blur="touchDetails('last_name')"
                                    />

                                    <TextField
                                        v-model="detailsForm.username"
                                        name="username"
                                        label="Username"
                                        autocomplete="username"
                                        :hint="USERNAME_HINT"
                                        :maxlength="USERNAME_MAX"
                                        :error="detailsErrors.username"
                                        @update:model-value="onUsernameInput"
                                        @blur="touchDetails('username')"
                                    />

                                    <TextField
                                        v-model="detailsForm.email"
                                        name="email"
                                        label="Email address"
                                        type="email"
                                        autocomplete="email"
                                        required
                                        :error="detailsErrors.email"
                                        @update:model-value="clearDetailsError('email')"
                                        @blur="touchDetails('email')"
                                    />

                                    <PhoneField
                                        v-model="detailsForm.phone"
                                        name="phone"
                                        label="Phone"
                                        placeholder="+1 555 555 0100"
                                        hint="Include the country code."
                                        :error="detailsErrors.phone"
                                        @update:model-value="clearDetailsError('phone')"
                                        @blur="touchDetails('phone')"
                                    />

                                    <DateField
                                        v-model="detailsForm.date_of_birth"
                                        name="date_of_birth"
                                        label="Date of birth"
                                        :max-date="today"
                                        :error="detailsErrors.date_of_birth"
                                        @update:model-value="clearDetailsError('date_of_birth')"
                                        @blur="touchDetails('date_of_birth')"
                                    />

                                    <SelectField
                                        v-model="detailsForm.gender"
                                        name="gender"
                                        label="Gender"
                                        placeholder="Not specified"
                                        :options="[
                                            { label: 'Male', value: 'male' },
                                            { label: 'Female', value: 'female' },
                                            { label: 'Other', value: 'other' },
                                        ]"
                                        :error="detailsErrors.gender"
                                        @update:model-value="clearDetailsError('gender')"
                                        @blur="touchDetails('gender')"
                                    />
                                </div>

                                <div class="pt-1">
                                    <AppButton
                                        type="submit"
                                        :loading="detailsForm.processing"
                                        :disabled="detailsForm.processing"
                                    >
                                        Save profile
                                    </AppButton>
                                </div>
                            </form>
                        </SettingsCard>
                    </div>
                </TabPanel>

                <TabPanel value="address">
                    <SettingsCard
                        title="Address"
                        description="A verified US address with coordinates helps routing, scheduling and territory assignment."
                        icon="pi pi-map-marker"
                    >
                        <template #action>
                            <Tag :severity="addressHasCoordinates ? 'success' : 'secondary'">
                                {{ addressHasCoordinates ? 'Google verified' : 'Manual' }}
                            </Tag>
                        </template>

                        <form
                            ref="addressFormEl"
                            class="flex flex-col gap-5"
                            novalidate
                            @submit.prevent="onAddressSubmit"
                        >
                            <FormErrorSummary :errors="addressErrors" :form-el="addressFormEl" />

                            <AddressSection
                                :api-key="props.googleMapsApiKey"
                                :form="addressForm"
                                :errors="addressErrors"
                                @blur="touchAddress"
                            />

                            <div>
                                <AppButton
                                    type="submit"
                                    :loading="addressForm.processing"
                                    :disabled="addressForm.processing"
                                >
                                    Save address
                                </AppButton>
                            </div>
                        </form>
                    </SettingsCard>
                </TabPanel>

                <TabPanel value="password">
                    <SettingsCard
                        title="Password"
                        description="Changing your password signs out other browsers on this account."
                        icon="pi pi-key"
                    >
                        <form
                            ref="passwordFormEl"
                            class="flex flex-col gap-5"
                            novalidate
                            @submit.prevent="onPasswordSubmit"
                        >
                            <FormErrorSummary :errors="passwordErrors" :form-el="passwordFormEl" />

                            <PasswordSection
                                :form="passwordForm"
                                :errors="passwordErrors"
                                @blur="touchPassword"
                            />

                            <div>
                                <AppButton
                                    type="submit"
                                    :loading="passwordForm.processing"
                                    :disabled="passwordForm.processing"
                                >
                                    Update password
                                </AppButton>
                            </div>
                        </form>
                    </SettingsCard>
                </TabPanel>

                <TabPanel value="security">
                    <SecuritySection
                        :enabled="twoFactorEnabled"
                        :confirmed="twoFactorConfirmed"
                        @update:enabled="twoFactorEnabled = $event"
                        @update:confirmed="twoFactorConfirmed = $event"
                    />
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>
