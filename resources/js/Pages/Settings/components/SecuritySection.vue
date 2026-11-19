<script setup lang="ts">
/**
 * SecuritySection — Fortify two-factor management on the account screen.
 *
 * The server owns the secret, the confirmation and the recovery codes. This
 * panel orchestrates the Fortify endpoints and keeps the destructive paths
 * behind a Dialog. Both the QR and the recovery codes sit behind
 * `password.confirm`; when that gate fires, the pending action is parked and
 * resumed after the password dialog succeeds.
 */
import { computed, onWatcherCleanup, ref, watch } from 'vue';
import { AppButton, FormErrorSummary, OtpField } from '@/Components/Forms';
import { useZodForm } from '@/lib/forms';
import {
    toTwoFactorError,
    TwoFactorRequestError,
    useTwoFactorSettings,
} from '@/Modules/Auth/composables/useTwoFactorSettings';
import { twoFactorCodeSchema } from '@/Modules/Users/schemas/profileSettings';
import Button from '@/volt/Button.vue';
import Dialog from '@/volt/Dialog.vue';
import Message from '@/volt/Message.vue';
import Skeleton from '@/volt/Skeleton.vue';
import Tag from '@/volt/Tag.vue';
import ConfirmPasswordDialog from './ConfirmPasswordDialog.vue';
import SettingsCard from './SettingsCard.vue';

const props = defineProps<{
    enabled: boolean;
    confirmed: boolean;
}>();

const emit = defineEmits<{
    'update:enabled': [enabled: boolean];
    'update:confirmed': [confirmed: boolean];
}>();

type PendingAction = () => Promise<void>;

const twoFactor = useTwoFactorSettings();

const pendingAction = ref<PendingAction | null>(null);
const confirmOpen = ref<boolean>(false);
const disableOpen = ref<boolean>(false);
const regenerateOpen = ref<boolean>(false);
const actionError = ref<string | null>(null);
const notice = ref<string | null>(null);
const copied = ref<boolean>(false);

const { form, errors, submit, touch, clearError } = useZodForm({
    initial: { code: '' },
    schema: twoFactorCodeSchema,
});

const status = computed<{ label: string; severity: 'success' | 'warn' | 'secondary' }>(() => {
    if (!props.enabled) {
        return { label: 'Off', severity: 'secondary' };
    }

    return props.confirmed
        ? { label: 'On', severity: 'success' }
        : { label: 'Setup incomplete', severity: 'warn' };
});

const qrPasswordGate = computed<boolean>(
    () => twoFactor.qrError.value?.code === 'password-required',
);

/*
 * Cancelling the password gate drops the parked action; otherwise a later
 * confirmation from a different flow could replay work the user abandoned.
 */
watch(confirmOpen, (open) => {
    if (!open) {
        pendingAction.value = null;
    }
});

const recoveryError = computed<string | null>(() => {
    const error = twoFactor.recoveryError.value;

    return error === null || error.code === 'password-required' ? null : error.message;
});

const run = async (action: PendingAction): Promise<void> => {
    actionError.value = null;
    notice.value = null;

    try {
        await action();
    } catch (caught) {
        const error = toTwoFactorError(caught);

        if (error.code === 'password-required') {
            pendingAction.value = action;
            confirmOpen.value = true;

            return;
        }

        actionError.value = error.message;
    }
};

const onPasswordConfirmed = (): void => {
    const action = pendingAction.value;
    pendingAction.value = null;
    confirmOpen.value = false;

    if (action !== null) {
        void run(action);
    }
};

/*
 * Both Fortify reads answer `423` behind `password.confirm`; the composable
 * stores that as a normal error value, so these two wrappers turn it back into
 * a throwable for the one `run()` gate above.
 */
const loadQrThroughGate = async (): Promise<void> => {
    await twoFactor.refreshQr();
    const error = twoFactor.qrError.value;

    if (error === null) {
        return;
    }

    if (error.code === 'password-required') {
        throw new TwoFactorRequestError('password-required', error.message);
    }

    throw new Error(error.message);
};

const loadCodesThroughGate = async (): Promise<void> => {
    await twoFactor.loadRecoveryCodes();
    const error = twoFactor.recoveryError.value;

    if (error === null) {
        return;
    }

    if (error.code === 'password-required') {
        throw new TwoFactorRequestError('password-required', error.message);
    }

    throw new Error(error.message);
};

const enable = (): void => {
    void run(async () => {
        await twoFactor.enable();
        emit('update:enabled', true);
        emit('update:confirmed', false);
        notice.value = 'Two-factor enabled. Scan the QR code, then confirm the six-digit code.';
    });
};

const retryQr = (): void => {
    void run(loadQrThroughGate);
};

const viewRecoveryCodes = (): void => {
    void run(loadCodesThroughGate);
};

const copyRecoveryCodes = (): void => {
    void copyCodes();
};

const onConfirmCode = (): void => {
    submit(() => {
        void run(async () => {
            try {
                await twoFactor.confirm(form.code);
                form.reset('code');
                emit('update:confirmed', true);
                notice.value = 'Two-factor authentication confirmed.';
            } catch (caught) {
                const error = toTwoFactorError(caught);

                if (error.fieldErrors.code !== undefined) {
                    actionError.value = error.fieldErrors.code;

                    return;
                }

                throw caught;
            }
        });
    });
};

const disable = (): void => {
    disableOpen.value = false;

    void run(async () => {
        await twoFactor.disable();
        emit('update:enabled', false);
        emit('update:confirmed', false);
        notice.value = 'Two-factor authentication disabled.';
    });
};

const regenerate = (): void => {
    regenerateOpen.value = false;

    void run(async () => {
        await twoFactor.regenerateRecoveryCodes();
        notice.value = 'New recovery codes generated. Store them somewhere safe.';
    });
};

const copyCodes = async (): Promise<void> => {
    try {
        await navigator.clipboard.writeText(twoFactor.recoveryCodes.value.join('\n'));
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        actionError.value =
            'The recovery codes could not be copied. Select them and copy manually.';
    }
};

const downloadCodes = (): void => {
    const blob = new Blob([`${twoFactor.recoveryCodes.value.join('\n')}\n`], {
        type: 'text/plain;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = 'aquashield-recovery-codes.txt';
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
};

watch(
    () => [props.enabled, props.confirmed] as const,
    ([enabled, confirmed]) => {
        if (!enabled || confirmed) {
            twoFactor.reset();

            return;
        }

        const controller = new AbortController();
        onWatcherCleanup(() => controller.abort());
        void twoFactor.loadQr(controller.signal);
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Message v-if="notice" severity="success" role="status">{{ notice }}</Message>
        <Message v-if="actionError" severity="error" role="alert">{{ actionError }}</Message>

        <SettingsCard
            title="Two-factor authentication"
            description="A one-time code from an authenticator app is required after your password."
            icon="pi pi-shield"
        >
            <template #action>
                <Tag :severity="status.severity">{{ status.label }}</Tag>
            </template>

            <div class="flex flex-col gap-5">
                <template v-if="!props.enabled">
                    <p class="text-sm text-surface-700 dark:text-surface-200">
                        Two-factor authentication adds a second lock to your account. Even if
                        someone learns your password, they still need the rotating code from your
                        phone.
                    </p>

                    <ul
                        class="grid gap-2 text-sm text-surface-600 sm:grid-cols-2 dark:text-surface-300"
                    >
                        <li class="flex items-start gap-2">
                            <i class="pi pi-verified mt-0.5 text-aq-success" aria-hidden="true" />
                            Works with 1Password, Authy, Google Authenticator and similar apps.
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="pi pi-key mt-0.5 text-aq-accent" aria-hidden="true" />
                            Recovery codes keep you in if the phone is lost.
                        </li>
                    </ul>

                    <div>
                        <AppButton
                            type="button"
                            shine
                            :loading="twoFactor.actionPending.value"
                            @click="enable"
                        >
                            <i class="pi pi-shield mr-2" aria-hidden="true" />
                            Enable two-factor authentication
                        </AppButton>
                    </div>
                </template>

                <div
                    v-else-if="!props.confirmed"
                    class="grid gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]"
                >
                    <div
                        class="flex flex-col items-center gap-3 rounded-xl border border-aq-shell-line bg-surface-50 p-4 dark:bg-surface-950/40"
                    >
                        <Skeleton v-if="twoFactor.qrLoading.value" class="size-44 rounded-lg" />

                        <img
                            v-else-if="twoFactor.qrDataUrl.value"
                            :src="twoFactor.qrDataUrl.value"
                            alt="QR code for your authenticator app"
                            class="size-44 rounded-lg bg-surface-0 p-2"
                            width="176"
                            height="176"
                        />

                        <div
                            v-else
                            class="flex size-44 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-surface-300 p-3 text-center dark:border-surface-700"
                        >
                            <i class="pi pi-shield text-lg text-surface-500" aria-hidden="true" />
                            <p class="text-xs text-surface-600 dark:text-surface-300">
                                {{
                                    twoFactor.qrError.value?.message ??
                                    'The QR code could not be loaded.'
                                }}
                            </p>

                            <Button
                                type="button"
                                text
                                size="small"
                                severity="secondary"
                                @click="retryQr"
                            >
                                <i
                                    class="mr-1.5 text-xs"
                                    :class="qrPasswordGate ? 'pi pi-lock' : 'pi pi-refresh'"
                                    aria-hidden="true"
                                />
                                {{ qrPasswordGate ? 'Confirm password' : 'Retry' }}
                            </Button>
                        </div>

                        <p class="text-center text-xs text-surface-600 dark:text-surface-300">
                            Scan with your authenticator app.
                        </p>
                    </div>

                    <form class="flex flex-col gap-4" novalidate @submit.prevent="onConfirmCode">
                        <FormErrorSummary :errors="errors" />

                        <OtpField
                            v-model="form.code"
                            name="code"
                            label="Authenticator code"
                            :length="6"
                            hint="Enter the six-digit code currently shown in the app."
                            required
                            :error="errors.code"
                            @update:model-value="clearError('code')"
                            @blur="touch('code')"
                        />

                        <div class="flex flex-wrap gap-2">
                            <AppButton
                                type="submit"
                                :loading="twoFactor.actionPending.value"
                                :disabled="twoFactor.actionPending.value"
                            >
                                Verify and turn on
                            </AppButton>

                            <Button
                                type="button"
                                text
                                severity="danger"
                                :disabled="twoFactor.actionPending.value"
                                @click="disableOpen = true"
                            >
                                Cancel setup
                            </Button>
                        </div>
                    </form>
                </div>

                <template v-else>
                    <p class="text-sm text-surface-700 dark:text-surface-200">
                        Two-factor authentication is active. Keep the recovery codes somewhere safe:
                        they are the only way back in if you lose the authenticator.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            outlined
                            severity="danger"
                            :disabled="twoFactor.actionPending.value"
                            @click="disableOpen = true"
                        >
                            <i class="pi pi-shield mr-2" aria-hidden="true" />
                            Disable two-factor
                        </Button>
                    </div>
                </template>
            </div>
        </SettingsCard>

        <SettingsCard
            v-if="props.enabled && props.confirmed"
            title="Recovery codes"
            description="Each code works once. Store them offline, not in the same place as your password."
            icon="pi pi-key"
        >
            <template v-if="twoFactor.recoveryLoaded.value" #action>
                <Tag severity="secondary">{{ twoFactor.recoveryCodes.value.length }} codes</Tag>
            </template>

            <div class="flex flex-col gap-4">
                <p v-if="recoveryError" role="alert" class="text-sm text-aq-danger-strong">
                    {{ recoveryError }}
                </p>

                <div v-if="!twoFactor.recoveryLoaded.value" class="flex flex-col items-start gap-3">
                    <p class="text-sm text-surface-700 dark:text-surface-200">
                        Recovery codes are hidden until you ask for them. Viewing them requires a
                        fresh password confirmation.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            outlined
                            :disabled="twoFactor.recoveryLoading.value"
                            @click="viewRecoveryCodes"
                        >
                            <i class="pi pi-key mr-2" aria-hidden="true" />
                            {{
                                twoFactor.recoveryLoading.value
                                    ? 'Loading codes…'
                                    : 'View recovery codes'
                            }}
                        </Button>

                        <Button
                            type="button"
                            text
                            severity="secondary"
                            :disabled="twoFactor.actionPending.value"
                            @click="regenerateOpen = true"
                        >
                            <i class="pi pi-refresh mr-2" aria-hidden="true" />
                            Regenerate
                        </Button>
                    </div>
                </div>

                <template v-else>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <code
                            v-for="code in twoFactor.recoveryCodes.value"
                            :key="code"
                            class="rounded-lg border border-aq-shell-line bg-surface-50 px-2 py-1.5 text-center font-mono text-xs text-surface-800 dark:bg-surface-950/50 dark:text-surface-100"
                        >
                            {{ code }}
                        </code>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="button" outlined @click="copyRecoveryCodes">
                            <i class="pi pi-copy mr-2" aria-hidden="true" />
                            {{ copied ? 'Copied' : 'Copy all' }}
                        </Button>

                        <Button type="button" outlined @click="downloadCodes">
                            <i class="pi pi-download mr-2" aria-hidden="true" />
                            Download .txt
                        </Button>

                        <Button
                            type="button"
                            text
                            severity="secondary"
                            :disabled="twoFactor.actionPending.value"
                            @click="regenerateOpen = true"
                        >
                            <i class="pi pi-refresh mr-2" aria-hidden="true" />
                            Regenerate
                        </Button>
                    </div>
                </template>
            </div>
        </SettingsCard>

        <Dialog
            v-model:visible="disableOpen"
            modal
            :header="
                props.confirmed ? 'Turn off two-factor authentication?' : 'Cancel two-factor setup?'
            "
            class="w-full max-w-md"
            :draggable="false"
        >
            <p class="text-sm text-surface-600 dark:text-surface-300">
                Your account will rely on the password alone. Recovery codes stop working
                immediately.
            </p>

            <template #footer>
                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        text
                        severity="secondary"
                        :disabled="twoFactor.actionPending.value"
                        @click="disableOpen = false"
                    >
                        Keep it on
                    </Button>

                    <Button
                        type="button"
                        severity="danger"
                        :disabled="twoFactor.actionPending.value"
                        @click="disable"
                    >
                        <i class="pi pi-shield mr-2" aria-hidden="true" />
                        Turn off
                    </Button>
                </div>
            </template>
        </Dialog>

        <Dialog
            v-model:visible="regenerateOpen"
            modal
            header="Regenerate recovery codes?"
            class="w-full max-w-md"
            :draggable="false"
        >
            <p class="text-sm text-surface-600 dark:text-surface-300">
                Every existing recovery code stops working. Any device that is signed in stays
                signed in.
            </p>

            <template #footer>
                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        text
                        severity="secondary"
                        :disabled="twoFactor.actionPending.value"
                        @click="regenerateOpen = false"
                    >
                        Cancel
                    </Button>

                    <Button
                        type="button"
                        :disabled="twoFactor.actionPending.value"
                        @click="regenerate"
                    >
                        <i class="pi pi-refresh mr-2" aria-hidden="true" />
                        Regenerate codes
                    </Button>
                </div>
            </template>
        </Dialog>

        <ConfirmPasswordDialog
            v-model:open="confirmOpen"
            :confirm="twoFactor.confirmPassword"
            @confirmed="onPasswordConfirmed"
        />
    </div>
</template>
