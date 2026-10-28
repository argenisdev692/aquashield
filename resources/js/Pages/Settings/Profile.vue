<script setup lang="ts">
import { computed, onWatcherCleanup, ref, useTemplateRef, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { z } from 'zod';
import {
    AddressAutocompleteField,
    AppButton,
    CheckboxField,
    DateField,
    FormErrorSummary,
    ImageDropzone,
    NumberField,
    PasswordField,
    PhoneField,
    TextField,
    type AddressSelection,
} from '@/Components/Forms';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH, passwordSchema, statusMessage } from '@/lib/auth';
import { useZodForm } from '@/lib/forms';
import { USERNAME_HINT, USERNAME_MAX, usernameSchema } from '@/lib/username';
import { useProfilePhotoUpload } from '@/Modules/Auth/composables/useProfilePhotoUpload';
import { useTwoFactorSettings } from '@/Modules/Auth/composables/useTwoFactorSettings';
import Message from '@/volt/Message.vue';

const props = defineProps<{
    user: App.Data.Auth.UserData;
    googleMapsApiKey: string;
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
    status?: string | null;
}>();

const confirm = useConfirm();
const today = new Date();

const profileSchema = z.object({
    first_name: z.string().min(1, 'Enter your first name').max(255),
    last_name: z.string().max(255),
    username: usernameSchema,
    email: z.email('Enter a valid email address').max(255),
    phone: z.string().max(32),
    date_of_birth: z
        .string()
        .nullable()
        .refine(
            (value) => value === null || value === '' || /^\d{4}-\d{2}-\d{2}$/.test(value),
            'Enter a valid date',
        ),
    address: z.string().max(255),
    address_2: z.string().max(255),
    zip_code: z.string().max(32),
    city: z.string().max(120),
    state: z.string().max(120),
    country: z.string().max(120),
    gender: z.string().max(50),
    latitude: z
        .number()
        .nullable()
        .refine(
            (value) => value === null || (value >= -90 && value <= 90),
            'Latitude must be between -90 and 90',
        ),
    longitude: z
        .number()
        .nullable()
        .refine(
            (value) => value === null || (value >= -180 && value <= 180),
            'Longitude must be between -180 and 180',
        ),
    terms_and_conditions: z.boolean(),
});

const passwordFormSchema = z
    .object({
        current_password: z.string().min(1, 'Enter your current password'),
        password: passwordSchema,
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match',
    });

const confirmSchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code from your authenticator app'),
});

const profileFormEl = useTemplateRef<HTMLFormElement>('profileForm');
const passwordFormEl = useTemplateRef<HTMLFormElement>('passwordForm');
const confirmFormEl = useTemplateRef<HTMLFormElement>('confirmForm');

const {
    form: profileForm,
    formEl: profileElement,
    errors: profileErrors,
    submit: submitProfile,
    touch: touchProfile,
    clearError: clearProfileError,
} = useZodForm({
    initial: {
        first_name: props.user.first_name,
        last_name: props.user.last_name ?? '',
        username: props.user.username ?? '',
        email: props.user.email,
        phone: props.user.phone ?? '',
        date_of_birth: props.user.date_of_birth,
        address: props.user.address ?? '',
        address_2: props.user.address_2 ?? '',
        zip_code: props.user.zip_code ?? '',
        city: props.user.city ?? '',
        state: props.user.state ?? '',
        country: props.user.country ?? '',
        gender: props.user.gender ?? '',
        latitude: props.user.latitude,
        longitude: props.user.longitude,
        terms_and_conditions: props.user.terms_and_conditions,
    },
    schema: profileSchema,
    formEl: profileFormEl,
});

const onUsernameInput = (): void => {
    profileForm.username = profileForm.username.toLowerCase();
    clearProfileError('username');
};

const {
    form: passwordForm,
    formEl: passwordElement,
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
    schema: passwordFormSchema,
    formEl: passwordFormEl,
});

const {
    form: confirmForm,
    formEl: confirmElement,
    errors: confirmErrors,
    submit: submitConfirm,
    touch: touchConfirm,
    clearError: clearConfirmError,
} = useZodForm({
    initial: { code: '' },
    schema: confirmSchema,
    formEl: confirmFormEl,
});

const twoFactorBusy = ref<boolean>(false);
const profilePhotoFile = ref<File | null>(null);

const {
    uploading: profilePhotoUploading,
    error: profilePhotoError,
    upload: uploadProfilePhoto,
    remove: removeProfilePhoto,
} = useProfilePhotoUpload();

const initials = computed<string>(() => {
    const parts = `${props.user.first_name} ${props.user.last_name ?? ''}`
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'AS'
    );
});

const {
    qrDataUrl,
    qrLoading,
    qrError,
    recoveryCodes,
    recoveryLoading,
    recoveryError,
    loadQr,
    loadRecoveryCodes,
    reset: resetTwoFactorSettings,
} = useTwoFactorSettings();

const flashMessage = computed<string | null>(() => statusMessage(props.status ?? null));

const onProfilePhotoUpload = (): void => {
    if (profilePhotoFile.value === null) {
        return;
    }

    const file = profilePhotoFile.value;

    void uploadProfilePhoto(file).then(() => {
        profilePhotoFile.value = null;
    });
};

const onProfilePhotoRemove = (): void => {
    void removeProfilePhoto();
};

const onAddressSelected = (selection: AddressSelection): void => {
    profileForm.address = selection.address;
    profileForm.zip_code = selection.zip_code;
    profileForm.city = selection.city;
    profileForm.state = selection.state;
    profileForm.country = selection.country;
    profileForm.latitude = selection.latitude;
    profileForm.longitude = selection.longitude;

    // address_2 is deliberately untouched: it is the manual apartment,
    // suite or unit line and must survive every new address selection.
    (['address', 'zip_code', 'city', 'state', 'country', 'latitude', 'longitude'] as const).forEach(
        (field) => {
            clearProfileError(field);
        },
    );
};

const onProfileSubmit = (): void => {
    submitProfile(() =>
        profileForm.put('/user/profile-information', {
            errorBag: 'updateProfileInformation',
            preserveScroll: true,
        }),
    );
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

const enableTwoFactor = (): void => {
    twoFactorBusy.value = true;

    router.post(
        '/user/two-factor-authentication',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                twoFactorBusy.value = false;
            },
        },
    );
};

const onConfirmSubmit = (): void => {
    submitConfirm(() =>
        confirmForm.post('/user/confirmed-two-factor-authentication', {
            errorBag: 'confirmTwoFactorAuthentication',
            preserveScroll: true,
            onSuccess: () => {
                confirmForm.reset('code');
                void loadRecoveryCodes();
            },
        }),
    );
};

const regenerateRecoveryCodes = (): void => {
    twoFactorBusy.value = true;

    router.post(
        '/user/two-factor-recovery-codes',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                void loadRecoveryCodes();
            },
            onFinish: () => {
                twoFactorBusy.value = false;
            },
        },
    );
};

const disableTwoFactor = (): void => {
    twoFactorBusy.value = true;

    router.delete('/user/two-factor-authentication', {
        preserveScroll: true,
        onSuccess: resetTwoFactorSettings,
        onFinish: () => {
            twoFactorBusy.value = false;
        },
    });
};

const askDisableTwoFactor = (): void => {
    confirm.require({
        header: 'Disable two-factor authentication',
        message:
            'Your account will rely on its password only. Recovery codes and the authenticator secret will be removed.',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: 'Disable 2FA' },
        rejectProps: { label: 'Cancel' },
        accept: disableTwoFactor,
    });
};

/**
 * The QR endpoint requires a recent password confirmation. Re-fetch whenever
 * the server tells the page that setup started (or ended) so a preserved
 * component does not keep a stale image.
 */
watch(
    () => [props.twoFactorEnabled, props.twoFactorConfirmed] as const,
    ([enabled, confirmed]) => {
        if (!enabled || confirmed) {
            qrDataUrl.value = null;
            qrError.value = null;
            return;
        }

        const controller = new AbortController();
        onWatcherCleanup(() => controller.abort());
        void loadQr(controller.signal);
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Profile & security" />

    <AppLayout title="Profile & security">
        <Message v-if="flashMessage" severity="success" class="mb-6">{{ flashMessage }}</Message>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-aq-line bg-aq-elevated/60 p-6 xl:col-span-2">
                <h2 class="text-lg font-semibold text-aq-ink">Profile information</h2>
                <p class="mt-1 text-sm text-aq-ink-muted">
                    Update the identity, contact and address details used across the workspace.
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-4">
                    <img
                        v-if="props.user.profile_photo_url"
                        :src="props.user.profile_photo_url"
                        alt=""
                        class="size-16 rounded-full border border-aq-line object-cover"
                    />
                    <span
                        v-else
                        aria-hidden="true"
                        class="grid size-16 place-items-center rounded-full bg-aq-accent/15 text-lg font-semibold text-aq-accent"
                    >
                        {{ initials }}
                    </span>

                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-aq-ink">Profile photo</p>
                        <p class="text-xs text-aq-ink-muted">
                            PNG, JPG or WebP, up to 2 MB. Stored on Cloudflare R2.
                        </p>
                    </div>
                </div>

                <div class="mt-4 max-w-md">
                    <ImageDropzone
                        v-model="profilePhotoFile"
                        label="Upload a new photo"
                        :max-size-mb="2"
                        :error="profilePhotoError ?? undefined"
                        :disabled="profilePhotoUploading"
                    />
                </div>

                <div class="mt-3 flex flex-wrap gap-3">
                    <AppButton
                        v-if="profilePhotoFile"
                        type="button"
                        :loading="profilePhotoUploading"
                        @click="onProfilePhotoUpload"
                    >
                        Save photo
                    </AppButton>
                    <AppButton
                        v-if="props.user.profile_photo_url"
                        type="button"
                        :loading="profilePhotoUploading"
                        @click="onProfilePhotoRemove"
                    >
                        Remove photo
                    </AppButton>
                </div>

                <form
                    ref="profileForm"
                    class="mt-6 flex flex-col gap-4"
                    novalidate
                    @submit.prevent="onProfileSubmit"
                >
                    <FormErrorSummary :errors="profileErrors" :form-el="profileElement" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <TextField
                            v-model="profileForm.first_name"
                            name="first_name"
                            label="First name"
                            autocomplete="given-name"
                            required
                            :error="profileErrors.first_name"
                            @update:model-value="clearProfileError('first_name')"
                            @blur="touchProfile('first_name')"
                        />

                        <TextField
                            v-model="profileForm.last_name"
                            name="last_name"
                            label="Last name"
                            autocomplete="family-name"
                            :error="profileErrors.last_name"
                            @update:model-value="clearProfileError('last_name')"
                            @blur="touchProfile('last_name')"
                        />

                        <TextField
                            v-model="profileForm.username"
                            name="username"
                            label="Username"
                            autocomplete="username"
                            :hint="USERNAME_HINT"
                            :maxlength="USERNAME_MAX"
                            :error="profileErrors.username"
                            @update:model-value="onUsernameInput"
                            @blur="touchProfile('username')"
                        />

                        <TextField
                            v-model="profileForm.email"
                            name="email"
                            label="Email"
                            type="email"
                            autocomplete="email"
                            required
                            :error="profileErrors.email"
                            @update:model-value="clearProfileError('email')"
                            @blur="touchProfile('email')"
                        />

                        <PhoneField
                            v-model="profileForm.phone"
                            name="phone"
                            label="Phone"
                            :error="profileErrors.phone"
                            @update:model-value="clearProfileError('phone')"
                            @blur="touchProfile('phone')"
                        />

                        <DateField
                            v-model="profileForm.date_of_birth"
                            name="date_of_birth"
                            label="Date of birth"
                            :max-date="today"
                            :error="profileErrors.date_of_birth"
                            @update:model-value="clearProfileError('date_of_birth')"
                            @blur="touchProfile('date_of_birth')"
                        />

                        <TextField
                            v-model="profileForm.gender"
                            name="gender"
                            label="Gender"
                            autocomplete="sex"
                            :error="profileErrors.gender"
                            @update:model-value="clearProfileError('gender')"
                            @blur="touchProfile('gender')"
                        />
                    </div>

                    <div class="mt-2 border-t border-aq-line pt-5">
                        <h3 class="text-sm font-semibold text-aq-ink">Address</h3>
                        <p class="mt-1 text-sm text-aq-ink-muted">
                            Start with the street address. Choosing a suggestion fills the postal
                            code, city, state, country and coordinates. The second address line
                            stays exactly as you type it.
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <AddressAutocompleteField
                                    v-model="profileForm.address"
                                    name="address"
                                    label="Address"
                                    :api-key="props.googleMapsApiKey"
                                    :error="profileErrors.address"
                                    @update:model-value="clearProfileError('address')"
                                    @select="onAddressSelected"
                                    @blur="touchProfile('address')"
                                />
                            </div>

                            <TextField
                                v-model="profileForm.address_2"
                                name="address_2"
                                label="Address line 2"
                                autocomplete="address-line2"
                                hint="Apartment, suite, unit, building, floor…"
                                :error="profileErrors.address_2"
                                @update:model-value="clearProfileError('address_2')"
                                @blur="touchProfile('address_2')"
                            />

                            <TextField
                                v-model="profileForm.zip_code"
                                name="zip_code"
                                label="ZIP / Postal code"
                                autocomplete="postal-code"
                                inputmode="numeric"
                                :error="profileErrors.zip_code"
                                @update:model-value="clearProfileError('zip_code')"
                                @blur="touchProfile('zip_code')"
                            />

                            <TextField
                                v-model="profileForm.city"
                                name="city"
                                label="City"
                                autocomplete="address-level2"
                                :error="profileErrors.city"
                                @update:model-value="clearProfileError('city')"
                                @blur="touchProfile('city')"
                            />

                            <TextField
                                v-model="profileForm.state"
                                name="state"
                                label="State / Province"
                                autocomplete="address-level1"
                                :error="profileErrors.state"
                                @update:model-value="clearProfileError('state')"
                                @blur="touchProfile('state')"
                            />

                            <TextField
                                v-model="profileForm.country"
                                name="country"
                                label="Country"
                                autocomplete="country-name"
                                :error="profileErrors.country"
                                @update:model-value="clearProfileError('country')"
                                @blur="touchProfile('country')"
                            />

                            <NumberField
                                v-model="profileForm.latitude"
                                name="latitude"
                                label="Latitude"
                                hint="Filled by the address search; edit only if you know the exact value."
                                :min="-90"
                                :max="90"
                                :step="0.000001"
                                :error="profileErrors.latitude"
                                @update:model-value="clearProfileError('latitude')"
                                @blur="touchProfile('latitude')"
                            />

                            <NumberField
                                v-model="profileForm.longitude"
                                name="longitude"
                                label="Longitude"
                                hint="Filled by the address search; edit only if you know the exact value."
                                :min="-180"
                                :max="180"
                                :step="0.000001"
                                :error="profileErrors.longitude"
                                @update:model-value="clearProfileError('longitude')"
                                @blur="touchProfile('longitude')"
                            />
                        </div>
                    </div>

                    <div class="mt-2 border-t border-aq-line pt-5">
                        <CheckboxField
                            v-model="profileForm.terms_and_conditions"
                            name="terms_and_conditions"
                            label="I accept the terms and conditions"
                            :error="profileErrors.terms_and_conditions"
                            @update:model-value="clearProfileError('terms_and_conditions')"
                            @blur="touchProfile('terms_and_conditions')"
                        />
                    </div>

                    <div class="mt-2">
                        <AppButton type="submit" :loading="profileForm.processing">
                            Save profile
                        </AppButton>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-aq-line bg-aq-elevated/60 p-6 xl:col-span-2">
                <h2 class="text-lg font-semibold text-aq-ink">Password</h2>
                <p class="mt-1 text-sm text-aq-ink-muted">{{ PASSWORD_HINT }}</p>

                <form
                    ref="passwordForm"
                    class="mt-5 flex max-w-xl flex-col gap-4"
                    novalidate
                    @submit.prevent="onPasswordSubmit"
                >
                    <FormErrorSummary :errors="passwordErrors" :form-el="passwordElement" />

                    <PasswordField
                        v-model="passwordForm.current_password"
                        name="current_password"
                        label="Current password"
                        autocomplete="current-password"
                        required
                        :error="passwordErrors.current_password"
                        @update:model-value="clearPasswordError('current_password')"
                        @blur="touchPassword('current_password')"
                    />

                    <PasswordField
                        v-model="passwordForm.password"
                        name="password"
                        label="New password"
                        autocomplete="new-password"
                        required
                        :hint="PASSWORD_HINT"
                        :maxlength="PASSWORD_MAX_LENGTH"
                        :error="passwordErrors.password"
                        @update:model-value="clearPasswordError('password')"
                        @blur="touchPassword('password')"
                    />

                    <PasswordField
                        v-model="passwordForm.password_confirmation"
                        name="password_confirmation"
                        label="Confirm new password"
                        autocomplete="new-password"
                        required
                        :maxlength="PASSWORD_MAX_LENGTH"
                        :error="passwordErrors.password_confirmation"
                        @update:model-value="clearPasswordError('password_confirmation')"
                        @blur="touchPassword('password_confirmation')"
                    />

                    <AppButton type="submit" :loading="passwordForm.processing">
                        Update password
                    </AppButton>
                </form>
            </section>

            <section class="rounded-xl border border-aq-line bg-aq-elevated/60 p-6 xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-aq-ink">Two-factor authentication</h2>
                        <p class="mt-1 max-w-2xl text-sm text-aq-ink-muted">
                            Add a six-digit code from Google Authenticator, Authy or Microsoft
                            Authenticator on top of your password. Recovery codes are shown once.
                        </p>
                    </div>

                    <span
                        class="rounded-full border border-aq-line px-3 py-1 text-xs font-semibold tracking-wide uppercase"
                        :class="props.twoFactorConfirmed ? 'text-aq-accent' : 'text-aq-ink-muted'"
                    >
                        {{ props.twoFactorConfirmed ? 'Enabled' : 'Not enabled' }}
                    </span>
                </div>

                <p class="mt-4 text-sm text-aq-ink-muted">
                    Changing these settings may ask you to confirm your password first.
                </p>

                <div v-if="!props.twoFactorEnabled" class="mt-5">
                    <AppButton type="button" :loading="twoFactorBusy" @click="enableTwoFactor">
                        Enable two-factor authentication
                    </AppButton>
                </div>

                <div v-else-if="!props.twoFactorConfirmed" class="mt-5 grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-aq-ink">1. Scan the QR code</h3>

                        <p v-if="qrLoading" class="mt-3 text-sm text-aq-ink-muted">
                            Loading the QR code…
                        </p>

                        <div v-else-if="qrError" class="mt-3">
                            <Message severity="error">{{ qrError }}</Message>
                            <p class="mt-2 text-sm text-aq-ink-muted">
                                Retrying asks for your password again, then reloads the code.
                            </p>
                            <AppButton
                                type="button"
                                :loading="twoFactorBusy"
                                class="mt-3"
                                @click="enableTwoFactor"
                            >
                                Retry setup
                            </AppButton>
                        </div>

                        <img
                            v-else-if="qrDataUrl"
                            :src="qrDataUrl"
                            alt="Two-factor authentication QR code"
                            class="mt-3 size-48 rounded-lg"
                            width="192"
                            height="192"
                        />
                    </div>

                    <form
                        ref="confirmForm"
                        class="flex flex-col gap-4"
                        novalidate
                        @submit.prevent="onConfirmSubmit"
                    >
                        <h3 class="text-sm font-semibold text-aq-ink">
                            2. Confirm the six-digit code
                        </h3>

                        <FormErrorSummary :errors="confirmErrors" :form-el="confirmElement" />

                        <TextField
                            v-model="confirmForm.code"
                            name="code"
                            label="Authenticator code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="6"
                            required
                            :error="confirmErrors.code"
                            @update:model-value="clearConfirmError('code')"
                            @blur="touchConfirm('code')"
                        />

                        <AppButton type="submit" :loading="confirmForm.processing">
                            Confirm and enable
                        </AppButton>
                    </form>
                </div>

                <div v-else class="mt-5 flex flex-col gap-4">
                    <div class="flex flex-wrap gap-3">
                        <AppButton
                            type="button"
                            :loading="recoveryLoading"
                            @click="loadRecoveryCodes"
                        >
                            Show recovery codes
                        </AppButton>

                        <AppButton
                            type="button"
                            :loading="twoFactorBusy"
                            @click="regenerateRecoveryCodes"
                        >
                            Regenerate recovery codes
                        </AppButton>

                        <AppButton
                            type="button"
                            :loading="twoFactorBusy"
                            @click="askDisableTwoFactor"
                        >
                            Disable two-factor authentication
                        </AppButton>
                    </div>

                    <Message v-if="recoveryError" severity="error">{{ recoveryError }}</Message>

                    <div
                        v-if="recoveryCodes.length > 0"
                        class="rounded-lg border border-aq-line bg-aq-overlay/40 p-4"
                    >
                        <h3 class="text-sm font-semibold text-aq-ink">Recovery codes</h3>
                        <p class="mt-1 text-sm text-aq-ink-muted">
                            Each code signs you in once if you lose the authenticator. Store them in
                            a password manager, not in this browser.
                        </p>
                        <ul class="mt-3 grid gap-1 font-mono text-sm text-aq-ink sm:grid-cols-2">
                            <li v-for="code in recoveryCodes" :key="code">
                                <code>{{ code }}</code>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
