// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';

/**
 * The month panel resolves weekly rule → exception → holiday into concrete
 * days. A day the client cannot read used to be filtered out, and a missing
 * day in that grid reads as "closed" — a silent business change. The hook must
 * fail into the panel's error boundary instead of shrinking the month.
 */
const { requestJson } = vi.hoisted(() => ({
    requestJson: vi.fn<(path: string, options?: unknown) => Promise<unknown>>(),
}));

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/http')>();

    return { ...actual, requestJson };
});

import { useAvailabilityWindow } from './useAvailabilityCalendar';

const day = (date: string) => ({
    date,
    is_open: true,
    source: 'weekly',
    reason: null,
    windows: [{ start: '09:00', end: '17:00' }],
});

const wrapper = ({ children }: { children: ReactNode }) => {
    const client = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    });

    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
};

beforeEach(() => {
    requestJson.mockReset();
});

afterEach(() => {
    cleanup();
});

describe('useAvailabilityWindow', () => {
    it('returns the resolved days of the window', async () => {
        requestJson.mockResolvedValueOnce({ data: [day('2026-11-02'), day('2026-11-03')] });

        const { result } = renderHook(() => useAvailabilityWindow('2026-11-01', '2026-11-30'), {
            wrapper,
        });

        await waitFor(() => expect(result.current.days).toHaveLength(2));

        expect(result.current.isError).toBe(false);
        expect(requestJson.mock.calls[0][0]).toContain('/availability/calendar?from=2026-11-01');
    });

    it('surfaces a malformed envelope as an error, not as an empty month', async () => {
        requestJson.mockResolvedValueOnce({ days: [] });

        const { result } = renderHook(() => useAvailabilityWindow('2026-11-01', '2026-11-30'), {
            wrapper,
        });

        await waitFor(() => expect(result.current.isError).toBe(true));
    });

    it('surfaces a single unreadable day instead of dropping it', async () => {
        requestJson.mockResolvedValueOnce({
            data: [day('2026-11-02'), { date: '2026-11-03', is_open: 'yes' }],
        });

        const { result } = renderHook(() => useAvailabilityWindow('2026-11-01', '2026-11-30'), {
            wrapper,
        });

        await waitFor(() => expect(result.current.isError).toBe(true));
        expect(result.current.days).toEqual([]);
    });

    it('forwards the region when one is given', async () => {
        requestJson.mockResolvedValueOnce({ data: [] });

        const { result } = renderHook(
            () => useAvailabilityWindow('2026-11-01', '2026-11-30', 'dallas'),
            { wrapper },
        );

        await waitFor(() => expect(result.current.isPending).toBe(false));

        expect(requestJson.mock.calls[0][0]).toContain('region=dallas');
    });

    it('does not fetch before both bounds exist', async () => {
        const { result } = renderHook(() => useAvailabilityWindow('', ''), { wrapper });

        await waitFor(() => expect(result.current.isPending).toBe(true));

        expect(requestJson).not.toHaveBeenCalled();
    });
});
