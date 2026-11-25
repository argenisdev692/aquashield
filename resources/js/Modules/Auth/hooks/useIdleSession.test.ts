// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, renderHook } from '@testing-library/react';

/**
 * The idle clock is a security control: the warning must appear on time, the
 * grace countdown must end in a real logout, and moving the mouse over the
 * dialog must NOT count as consent — otherwise an abandoned tab keeps a
 * session alive forever.
 */
const { performLogout } = vi.hoisted(() => ({ performLogout: vi.fn<() => void>() }));

vi.mock('@/lib/auth', () => ({
    useLogout: () => performLogout,
}));

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/http')>();

    return { ...actual, xsrfToken: () => 'test-token' };
});

import { useIdleSession } from './useIdleSession';

const fetchMock = vi.fn<() => Promise<{ status: number }>>(() => Promise.resolve({ status: 204 }));

const mount = () => renderHook(() => useIdleSession(3, 2, '/auth/keep-alive', '/logout'));

beforeEach(() => {
    vi.useFakeTimers();
    performLogout.mockReset();
    fetchMock.mockClear();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('useIdleSession', () => {
    it('stays hidden while the user is inside the warn window', () => {
        const { result } = mount();

        act(() => {
            vi.advanceTimersByTime(2_000);
        });

        expect(result.current.visible).toBe(false);
        expect(performLogout).not.toHaveBeenCalled();
    });

    it('warns at the threshold and counts the grace period down', () => {
        const { result } = mount();

        act(() => {
            vi.advanceTimersByTime(3_000);
        });

        expect(result.current.visible).toBe(true);
        expect(result.current.remainingSeconds).toBe(2);

        act(() => {
            vi.advanceTimersByTime(1_000);
        });

        expect(result.current.remainingSeconds).toBe(1);
    });

    it('ignores activity while the dialog is open', () => {
        const { result } = mount();

        act(() => {
            vi.advanceTimersByTime(3_000);
        });

        act(() => {
            window.dispatchEvent(new Event('pointerdown'));
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }));
        });

        act(() => {
            vi.advanceTimersByTime(2_000);
        });

        expect(performLogout).toHaveBeenCalledTimes(1);
        // The timer keeps counting after logout; the guard makes it a no-op.
        expect(result.current.visible).toBe(true);
    });

    it('logs out exactly once when the grace period expires', () => {
        mount();

        act(() => {
            vi.advanceTimersByTime(3_000);
        });

        act(() => {
            vi.advanceTimersByTime(2_000);
        });

        expect(performLogout).toHaveBeenCalledTimes(1);

        act(() => {
            vi.advanceTimersByTime(10_000);
        });

        expect(performLogout).toHaveBeenCalledTimes(1);
    });

    it('keeps the session alive on demand and hides the warning', async () => {
        const { result } = mount();

        act(() => {
            vi.advanceTimersByTime(3_000);
        });

        await act(async () => {
            await result.current.keepSession();
        });

        expect(fetchMock).toHaveBeenCalledWith('/auth/keep-alive', expect.anything());
        expect(result.current.visible).toBe(false);
        expect(performLogout).not.toHaveBeenCalled();
    });

    it('resets the activity clock on a real interaction before the warning', () => {
        const { result } = mount();

        act(() => {
            vi.advanceTimersByTime(2_000);
            window.dispatchEvent(new Event('pointerdown'));
        });

        act(() => {
            vi.advanceTimersByTime(2_000);
        });

        // 4s total, but only 2s since the interaction.
        expect(result.current.visible).toBe(false);
    });
});
