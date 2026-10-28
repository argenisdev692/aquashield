<script setup lang="ts">
import { computed } from 'vue';
import { AppButton } from '@/Components/Forms';
import { useIdleSession } from '@/Modules/Auth/composables/useIdleSession';
import Dialog from '@/volt/Dialog.vue';
import Message from '@/volt/Message.vue';

/**
 * Idle-session dialog.
 *
 * It is mounted once, by AppLayout, for every authenticated page. The timer
 * and the transport live in `useIdleSession`; this file only renders the
 * decision. Closing is intentionally disabled: the two exits are "keep" and
 * "sign out", and doing nothing ends in the second one.
 */

const {
    warnAfterSeconds = 300,
    graceSeconds = 60,
    keepAliveEndpoint = '/auth/keep-alive',
    logoutEndpoint = '/logout',
} = defineProps<{
    warnAfterSeconds?: number;
    graceSeconds?: number;
    keepAliveEndpoint?: string;
    logoutEndpoint?: string;
}>();

const { visible, remainingSeconds, keeping, keepSession, logout } = useIdleSession(
    warnAfterSeconds,
    graceSeconds,
    keepAliveEndpoint,
    logoutEndpoint,
);

const formattedRemaining = computed<string>(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :closable="false"
        :close-on-escape="false"
        :dismissable-mask="false"
        header="Your session is inactive"
        :style="{ width: '28rem' }"
        class="rounded-2xl"
    >
        <Message severity="warn" :closable="false" class="mb-4">
            We have not seen any activity for a while. For your security, this session will end
            automatically.
        </Message>

        <p class="text-sm leading-relaxed text-aq-ink-muted">
            You will be signed out in
            <span class="font-semibold tabular-nums text-aq-ink" role="timer" aria-live="off">
                {{ formattedRemaining }}
            </span>
            . Choose an option to continue.
        </p>

        <template #footer>
            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-xl border border-aq-line bg-aq-elevated/40 px-4 text-sm font-semibold text-aq-ink transition-colors duration-150 hover:border-aq-line-strong hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                    @click="logout"
                >
                    Sign out
                </button>

                <AppButton shine :loading="keeping" @click="keepSession">
                    Keep me signed in
                </AppButton>
            </div>
        </template>
    </Dialog>
</template>
