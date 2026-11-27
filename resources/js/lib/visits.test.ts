// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, renderHook } from '@testing-library/react';

type RouterCallback = (...args: unknown[]) => void;
type ReloadOptions = {
    only?: string[];
    onCancelToken?: (token: { cancel: () => void }) => void;
    onFinish?: () => void;
};

const { handlers, reload } = vi.hoisted(() => ({
    handlers: new Map<string, Set<RouterCallback>>(),
    reload: vi.fn<(options: ReloadOptions) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        on: vi.fn<(type: string, callback: RouterCallback) => () => void>((type, callback) => {
            const callbacks = handlers.get(type) ?? new Set<RouterCallback>();
            callbacks.add(callback);
            handlers.set(type, callbacks);

            return () => {
                callbacks.delete(callback);
            };
        }),
        reload,
    },
}));

beforeEach(() => {
    vi.resetModules();
    handlers.clear();
    reload.mockReset();
    window.history.replaceState({}, '', '/backups');
});

afterEach(() => {
    cleanup();
});

const emit = (type: string): void => {
    handlers.get(type)?.forEach((callback) => callback());
};

describe('Inertia visit coordination', () => {
    it('stays pending until every overlapping visit finishes', async () => {
        const { useVisitPending } = await import('./visits');
        const { result } = renderHook(() => useVisitPending());

        act(() => {
            emit('start');
            emit('start');
        });
        expect(result.current).toBe(true);

        act(() => emit('finish'));
        expect(result.current).toBe(true);

        act(() => emit('finish'));
        expect(result.current).toBe(false);
    });

    it('cancels the current background reload before a newer table visit', async () => {
        const { cancelPendingReload, reloadCurrentPage } = await import('./visits');
        reloadCurrentPage({ only: ['rows'] });
        const options = reload.mock.calls[0]?.[0];
        const cancel = vi.fn<() => void>();
        options?.onCancelToken?.({ cancel });

        cancelPendingReload('/backups');

        expect(cancel).toHaveBeenCalledTimes(1);
    });

    it('supersedes an older reload instead of allowing both responses to commit', async () => {
        const { reloadCurrentPage } = await import('./visits');
        reloadCurrentPage({ only: ['rows'] });
        const first = reload.mock.calls[0]?.[0];
        const cancelFirst = vi.fn<() => void>();
        first?.onCancelToken?.({ cancel: cancelFirst });

        reloadCurrentPage({ only: ['total'] });
        const second = reload.mock.calls[1]?.[0];
        const cancelSecond = vi.fn<() => void>();
        second?.onCancelToken?.({ cancel: cancelSecond });

        expect(cancelFirst).toHaveBeenCalledTimes(1);

        act(() => first?.onFinish?.());
        expect(cancelSecond).not.toHaveBeenCalled();
    });
});
