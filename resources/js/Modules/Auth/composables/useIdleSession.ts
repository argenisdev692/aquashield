import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { xsrfToken } from '@/lib/http';

/**
 * Client half of the idle-session policy.
 *
 * The composable owns three things the page must not: the activity clock, the
 * heartbeat that keeps the server's `auth.last_activity_at` in sync while the
 * user is genuinely active, and the countdown that ends in a real logout.
 *
 * Moving the mouse while the dialog is open is NOT consent — the dialog
 * deliberately ignores activity until the user clicks "Keep me signed in", so
 * an abandoned tab always signs out after the grace period.
 */

export interface IdleSession {
    visible: Ref<boolean>;
    remainingSeconds: Ref<number>;
    keeping: Ref<boolean>;
    keepSession: () => Promise<void>;
    logout: () => void;
}

const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'touchstart', 'scroll'] as const;
const TICK_MS = 1_000;
const HEARTBEAT_MS = 60_000;

export function useIdleSession(
    warnAfterSeconds: number,
    graceSeconds: number,
    keepAliveEndpoint: string,
    logoutEndpoint: string,
): IdleSession {
    const visible = ref(false);
    const remainingSeconds = ref(graceSeconds);
    const keeping = ref(false);

    let lastActivity = Date.now();
    let lastHeartbeat = Date.now();
    let deadline = 0;
    let interval: number | undefined;
    let logoutStarted = false;

    const post = async (url: string): Promise<void> => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });

        if (response.status === 401 || response.status === 419) {
            // The server already invalidated the session (or the CSRF token
            // rotated). A hard visit is the only clean recovery.
            window.location.assign('/login');
        }
    };

    const heartbeat = async (): Promise<void> => {
        lastHeartbeat = Date.now();

        try {
            await post(keepAliveEndpoint);
        } catch {
            // A transient network failure is not a reason to sign out; the
            // next tick retries while the user remains active.
        }
    };

    const reset = (): void => {
        const now = Date.now();
        lastActivity = now;
        lastHeartbeat = now;
        deadline = 0;
        remainingSeconds.value = graceSeconds;
        visible.value = false;
    };

    const keepSession = async (): Promise<void> => {
        keeping.value = true;

        try {
            await post(keepAliveEndpoint);
            reset();
        } finally {
            keeping.value = false;
        }
    };

    const logout = (): void => {
        if (logoutStarted) {
            return;
        }

        logoutStarted = true;
        router.post(logoutEndpoint);
    };

    const onActivity = (): void => {
        if (visible.value) {
            return;
        }

        lastActivity = Date.now();
    };

    const tick = (): void => {
        if (logoutStarted) {
            return;
        }

        const now = Date.now();
        const idleSeconds = Math.floor((now - lastActivity) / 1_000);

        if (!visible.value && idleSeconds >= warnAfterSeconds) {
            visible.value = true;
            deadline = now + graceSeconds * 1_000;
            remainingSeconds.value = graceSeconds;

            // Align the server clock at warning time. The server still keeps
            // its own deadline, so ignoring the dialog ends in the same place.
            void heartbeat();

            return;
        }

        if (visible.value) {
            remainingSeconds.value = Math.max(0, Math.ceil((deadline - now) / 1_000));

            if (remainingSeconds.value === 0) {
                logout();
            }

            return;
        }

        if (idleSeconds < warnAfterSeconds && now - lastHeartbeat >= HEARTBEAT_MS) {
            void heartbeat();
        }
    };

    onMounted(() => {
        for (const event of ACTIVITY_EVENTS) {
            window.addEventListener(event, onActivity, { passive: true });
        }

        interval = window.setInterval(tick, TICK_MS);
    });

    onUnmounted(() => {
        for (const event of ACTIVITY_EVENTS) {
            window.removeEventListener(event, onActivity);
        }

        if (interval !== undefined) {
            window.clearInterval(interval);
        }
    });

    return { visible, remainingSeconds, keeping, keepSession, logout };
}
