import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { useLogout } from '@/lib/auth';
import { xsrfToken } from '@/lib/http';

/**
 * Client half of the idle-session policy.
 *
 * The hook owns three things the page must not: the activity clock, the
 * heartbeat that keeps the server's `auth.last_activity_at` in sync while the
 * user is genuinely active, and the countdown that ends in a real logout.
 *
 * Moving the mouse while the dialog is open is NOT consent — the dialog
 * deliberately ignores activity until the user clicks "Keep me signed in", so
 * an abandoned tab always signs out after the grace period.
 *
 * The interval and window listeners are registered once. `tick` is an Effect
 * Event, so the timer reads the latest grace/heartbeat/logout values without
 * putting them in the effect dependency list.
 */

export interface IdleSession {
    visible: boolean;
    remainingSeconds: number;
    keeping: boolean;
    /** A failed "keep working" attempt; the dialog stays open so it can be retried. */
    keepError: string | null;
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
    const [visible, setVisible] = useState(false);
    const [remainingSeconds, setRemainingSeconds] = useState(graceSeconds);
    const [keeping, setKeeping] = useState(false);
    const [keepError, setKeepError] = useState<string | null>(null);

    const visibleRef = useRef(false);
    /*
     * Initialised in the mount effect, not here: `Date.now()` during render is
     * an impure call (React Compiler skips a component that reads the clock
     * while rendering), and the clock only has to be correct before the first
     * one-second tick.
     */
    const lastActivityRef = useRef(0);
    const lastHeartbeatRef = useRef(0);
    const deadlineRef = useRef(0);
    const logoutStartedRef = useRef(false);
    const performLogout = useLogout(logoutEndpoint);

    const post = async (url: string): Promise<void> => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            // A hung keep-alive must not leave the dialog "keeping" forever.
            signal: AbortSignal.timeout(10_000),
        });

        if (response.status === 401 || response.status === 419) {
            // The server already invalidated the session (or the CSRF token
            // rotated). A hard visit is the only clean recovery.
            window.location.assign('/login');

            throw new Error('Your session expired.');
        }

        /*
         * Every other non-2xx is a real failure. Returning normally here made
         * a 5xx look like a successful keep-alive: the dialog closed and the
         * local clock restarted while the server deadline stayed put.
         */
        if (!response.ok) {
            throw new Error('The keep-alive request was rejected.');
        }
    };

    const heartbeat = async (): Promise<void> => {
        lastHeartbeatRef.current = Date.now();

        try {
            await post(keepAliveEndpoint);
        } catch {
            // A transient network failure is not a reason to sign out; the
            // next tick retries while the user remains active.
        }
    };

    const logout = (): void => {
        if (logoutStartedRef.current) {
            return;
        }

        logoutStartedRef.current = true;
        performLogout();
    };

    const reset = (): void => {
        const now = Date.now();
        lastActivityRef.current = now;
        lastHeartbeatRef.current = now;
        deadlineRef.current = 0;
        setRemainingSeconds(graceSeconds);
        setKeepError(null);
        setVisible(false);
        visibleRef.current = false;
    };

    const keepSession = async (): Promise<void> => {
        setKeeping(true);
        setKeepError(null);

        try {
            await post(keepAliveEndpoint);
            reset();
        } catch {
            /*
             * Keep the dialog open and re-arm the grace window. Closing it on
             * failure told the user the session was extended, then signed them
             * out on the next request.
             */
            deadlineRef.current = Date.now() + graceSeconds * 1_000;
            setRemainingSeconds(graceSeconds);
            setKeepError(
                'The session could not be kept alive. Check your connection and try again.',
            );
        } finally {
            setKeeping(false);
        }
    };

    const tick = useEffectEvent((): void => {
        if (logoutStartedRef.current) {
            return;
        }

        const now = Date.now();
        const idleSeconds = Math.floor((now - lastActivityRef.current) / 1_000);

        if (!visibleRef.current && idleSeconds >= warnAfterSeconds) {
            visibleRef.current = true;
            deadlineRef.current = now + graceSeconds * 1_000;
            setVisible(true);
            setRemainingSeconds(graceSeconds);

            /*
             * Do NOT heartbeat here. EnforceIdleSession writes
             * `auth.last_activity_at` on every authenticated request, so a
             * warning-time ping would restart the server window without the
             * user's consent. Only the explicit "Keep working" click may
             * extend the session.
             */

            return;
        }

        if (visibleRef.current) {
            const remaining = Math.max(0, Math.ceil((deadlineRef.current - now) / 1_000));
            setRemainingSeconds(remaining);

            if (remaining === 0) {
                logout();
            }

            return;
        }

        if (idleSeconds < warnAfterSeconds && now - lastHeartbeatRef.current >= HEARTBEAT_MS) {
            void heartbeat();
        }
    });

    useEffect(() => {
        const mountedAt = Date.now();
        lastActivityRef.current = mountedAt;
        lastHeartbeatRef.current = mountedAt;

        const handleActivity = (): void => {
            // Activity while the warning is visible is not consent; only the
            // explicit "Keep me signed in" button resets the deadline.
            if (visibleRef.current) {
                return;
            }

            lastActivityRef.current = Date.now();
        };

        for (const event of ACTIVITY_EVENTS) {
            window.addEventListener(event, handleActivity, { passive: true });
        }

        const interval = window.setInterval(tick, TICK_MS);

        return () => {
            for (const event of ACTIVITY_EVENTS) {
                window.removeEventListener(event, handleActivity);
            }

            window.clearInterval(interval);
        };
    }, []);

    return { visible, remainingSeconds, keeping, keepError, keepSession, logout };
}
