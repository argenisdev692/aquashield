<script setup lang="ts">
import { computed, onWatcherCleanup, ref, useTemplateRef, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { z } from 'zod';
import {
    AppButton,
    FormErrorSummary,
    ImageDropzone,
    PasswordField,
    TextField,
} from '@/Components/Forms';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH, passwordSchema, statusMessage } from '@/lib/auth';
import { useZodForm } from '@/lib/forms';
import { useAvatarUpload } from '@/Modules/Auth/composables/useAvatarUpload';
import { useTwoFactorSettings } from '@/Modules/Auth/composables/useTwoFactorSettings';
import Message from '@/volt/Message.vue';

const props = defineProps<{
    user: App.Data.Auth.UserData;
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
    status?: string | null;
}>();

const confirm = useConfirm();

const profileSchema = z.object({
    name: z.string().min(2, 'Enter your name').max(255),
    email: z.email('Enter a valid email address').max(255),
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
        name: props.user.name,
        email: props.user.email,
    },
    schema: profileSchema,
    formEl: profileFormEl,
});

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
const avatarFile = ref<File | null>(null);

const {
    uploading: avatarUploading,
    error: avatarError,
    upload: uploadAvatar,
    remove: removeAvatar,
} = useAvatarUpload();

const initials = computed<string>(() => {
    const parts = props.user.name.trim().split(/\s+/).filter(Boolean);

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

const onAvatarUpload = (): void => {
    if (avatarFile.value === null) {
        return;
    }

    const file = avatarFile.value;

    void uploadAvatar(file).then(() => {
        avatarFile.value = null;
    });
};

const onAvatarRemove = (): void => {
    void removeAvatar();
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
            <section class="rounded-xl border border-aq-line bg-aq-elevated/60 p-6">
                <h2 class="text-lg font-semibold text-aq-ink">Profile information</h2>
                <p class="mt-1 text-sm text-aq-ink-muted">
                    Update the name and email address used across the workspace.
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-4">
                    <img
                        v-if="props.user.avatar_url"
                        :src="props.user.avatar_url"
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

                <div class="mt-4">
                    <ImageDropzone
                        v-model="avatarFile"
                        label="Upload a new photo"
                        :max-size-mb="2"
                        :error="avatarError ?? undefined"
                        :disabled="avatarUploading"
                    />
                </div>

                <div class="mt-3 flex flex-wrap gap-3">
                    <AppButton
                        v-if="avatarFile"
                        type="button"
                        :loading="avatarUploading"
                        @click="onAvatarUpload"
                    >
                        Save photo
                    </AppButton>
                    <AppButton
                        v-if="props.user.avatar_url"
                        type="button"
                        :loading="avatarUploading"
                        @click="onAvatarRemove"
                    >
                        Remove photo
                    </AppButton>
                </div>

                <form
                    ref="profileForm"
                    class="mt-5 flex flex-col gap-4"
                    novalidate
                    @submit.prevent="onProfileSubmit"
                >
                    <FormErrorSummary :errors="profileErrors" :form-el="profileElement" />

                    <TextField
                        v-model="profileForm.name"
                        name="name"
                        label="Name"
                        autocomplete="name"
                        required
                        :error="profileErrors.name"
                        @update:model-value="clearProfileError('name')"
                        @blur="touchProfile('name')"
                    />

                    <TextField
                        v-model="profileForm.email"
                        name="email"
                        label="Email"
                        type="email"
                        autocomplete="username"
                        required
                        :error="profileErrors.email"
                        @update:model-value="clearProfileError('email')"
                        @blur="touchProfile('email')"
                    />

                    <AppButton type="submit" :loading="profileForm.processing">
                        Save profile
                    </AppButton>
                </form>
            </section>

            <section class="rounded-xl border border-aq-line bg-aq-elevated/60 p-6">
                <h2 class="text-lg font-semibold text-aq-ink">Password</h2>
                <p class="mt-1 text-sm text-aq-ink-muted">{{ PASSWORD_HINT }}</p>

                <form
                    ref="passwordForm"
                    class="mt-5 flex flex-col gap-4"
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
