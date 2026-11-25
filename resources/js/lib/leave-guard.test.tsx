// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';

const { on, visit } = vi.hoisted(() => ({
    on: vi.fn<(type: string, callback: (event: CustomEvent<unknown>) => void) => () => void>(),
    visit: vi.fn<(url: string | URL) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { on, visit },
}));

import { useNavigationGuard } from './leave-guard';

type BeforeHandler = (event: CustomEvent<unknown>) => void;

const beforeHandler = (): BeforeHandler => {
    const handler = on.mock.calls[0]?.[1];

    if (handler === undefined) {
        throw new Error('The navigation guard did not subscribe to the before event.');
    }

    return handler;
};

const beforeEvent = (
    url: string,
    method = 'get',
): { event: CustomEvent<unknown>; preventDefault: ReturnType<typeof vi.fn> } => {
    const preventDefault = vi.fn<() => void>();

    return {
        preventDefault,
        event: {
            preventDefault,
            detail: { visit: { url: new URL(url), method } },
        } as unknown as CustomEvent<unknown>,
    };
};

beforeEach(() => {
    on.mockReset();
    visit.mockReset();
    on.mockReturnValue(vi.fn());
});

describe('useNavigationGuard', () => {
    it('blocks a dirty page visit and exposes the destination', () => {
        const { result } = renderHook(() => useNavigationGuard(true));
        const { event, preventDefault } = beforeEvent('http://localhost/roles');

        act(() => {
            beforeHandler()(event);
        });

        expect(preventDefault).toHaveBeenCalledTimes(1);
        expect(result.current.pendingUrl).toBe('http://localhost/roles');
    });

    it('lets the form submit its own POST while dirty', () => {
        renderHook(() => useNavigationGuard(true));
        const { event, preventDefault } = beforeEvent('http://localhost/roles', 'post');

        act(() => {
            beforeHandler()(event);
        });

        expect(preventDefault).not.toHaveBeenCalled();
    });

    it('confirms by visiting once, without re-blocking the same navigation', () => {
        const { result } = renderHook(() => useNavigationGuard(true));

        act(() => {
            beforeHandler()(beforeEvent('http://localhost/roles').event);
        });

        act(() => {
            result.current.confirmLeave();
        });

        expect(visit).toHaveBeenCalledWith('http://localhost/roles');

        const allowed = beforeEvent('http://localhost/roles');

        act(() => {
            beforeHandler()(allowed.event);
        });

        expect(allowed.preventDefault).not.toHaveBeenCalled();
    });

    it('cancels without navigating', () => {
        const { result } = renderHook(() => useNavigationGuard(true));

        act(() => {
            beforeHandler()(beforeEvent('http://localhost/roles').event);
        });

        act(() => {
            result.current.cancelLeave();
        });

        expect(result.current.pendingUrl).toBeNull();
        expect(visit).not.toHaveBeenCalled();
    });

    it('does not block a clean form', () => {
        renderHook(() => useNavigationGuard(false));
        const { event, preventDefault } = beforeEvent('http://localhost/roles');

        act(() => {
            beforeHandler()(event);
        });

        expect(preventDefault).not.toHaveBeenCalled();
    });
});
