// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';

const { requestJson } = vi.hoisted(() => ({
    requestJson: vi.fn<(url: string, options?: { signal?: AbortSignal }) => Promise<unknown>>(),
}));

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/http')>();

    return { ...actual, requestJson };
});

import { useUserAvailability } from './useUserAvailability';

const makeWrapper = () => {
    const client = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    return ({ children }: { children: ReactNode }) => (
        <QueryClientProvider client={client}>{children}</QueryClientProvider>
    );
};

const lastCall = () => requestJson.mock.calls.at(-1) as [string, { signal?: AbortSignal }];

beforeEach(() => {
    requestJson.mockReset();
});

describe('useUserAvailability', () => {
    it('probes a valid value once, normalised, with an abort signal', async () => {
        requestJson.mockResolvedValueOnce({
            available: true,
            message: 'Email is available.',
        });

        const { result } = renderHook(() => useUserAvailability('email', ' ana@gmail.com '), {
            wrapper: makeWrapper(),
        });

        await waitFor(() => expect(result.current.state).toBe('available'));

        expect(requestJson).toHaveBeenCalledTimes(1);
        expect(result.current.message).toBe('Email is available.');

        const [url, options] = lastCall();

        expect(url).toBe('/users/availability?field=email&value=ana%40gmail.com');
        expect(options.signal).toBeInstanceOf(AbortSignal);
    });

    it('debounces typing instead of firing per keystroke', async () => {
        requestJson.mockResolvedValue({ available: true, message: 'Available.' });

        const { rerender } = renderHook(
            ({ value }: { value: string }) => useUserAvailability('email', value),
            { wrapper: makeWrapper(), initialProps: { value: 'ana@gmail.com' } },
        );

        await waitFor(() => expect(requestJson).toHaveBeenCalledTimes(1));

        rerender({ value: 'bea@gmail.com' });

        expect(requestJson).toHaveBeenCalledTimes(1);

        await waitFor(() => expect(requestJson).toHaveBeenCalledTimes(2), { timeout: 2_000 });
    });

    it('reports a reserved username without a request', () => {
        const { result } = renderHook(() => useUserAvailability('username', 'admin'), {
            wrapper: makeWrapper(),
        });

        expect(result.current.state).toBe('unavailable');
        expect(result.current.message).toMatch(/reserved/i);
        expect(requestJson).not.toHaveBeenCalled();
    });

    it('stays idle for a value that is not a valid email', () => {
        const { result } = renderHook(() => useUserAvailability('email', 'not-an-email'), {
            wrapper: makeWrapper(),
        });

        expect(result.current).toEqual({ state: 'idle', message: '' });
        expect(requestJson).not.toHaveBeenCalled();
    });

    it('surfaces a failed probe as an error state', async () => {
        requestJson.mockRejectedValueOnce(new Error('network'));

        const { result } = renderHook(() => useUserAvailability('email', 'ana@gmail.com'), {
            wrapper: makeWrapper(),
        });

        await waitFor(() => expect(result.current.state).toBe('error'));

        expect(result.current.message).toMatch(/Could not verify/);
    });

    it('does not probe an unchanged edit value when disabled', () => {
        const { result } = renderHook(
            () => useUserAvailability('email', 'ana@gmail.com', 'uuid-1', false),
            { wrapper: makeWrapper() },
        );

        expect(result.current).toEqual({ state: 'idle', message: '' });
        expect(requestJson).not.toHaveBeenCalled();
    });
});
