// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, renderHook } from '@testing-library/react';

const { pageProps, reloadCurrentPage, requestJson } = vi.hoisted(() => ({
    pageProps: {
        runStatus: {
            status: 'idle' as Modules.Backups.Domain.Enums.BackupRunStatus,
            updated_at: null,
        },
    },
    reloadCurrentPage: vi.fn<(options?: unknown) => void>(),
    requestJson: vi.fn<(path: string, options?: unknown) => Promise<unknown>>(),
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@/lib/visits', () => ({ reloadCurrentPage }));

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/http')>();

    return { ...actual, requestJson };
});

import { useRunBackup } from './useRunBackup';

const tableProps = ['rows', 'total', 'currentPage', 'perPage', 'lastPage', 'filters', 'disks'];

beforeEach(() => {
    vi.useFakeTimers();
    pageProps.runStatus.status = 'idle';
    reloadCurrentPage.mockReset();
    requestJson.mockReset();
    requestJson.mockResolvedValue({ status: 'queued' });
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

const advancePoll = async (milliseconds = 5_000): Promise<void> => {
    await act(async () => {
        vi.advanceTimersByTime(milliseconds);
        await Promise.resolve();
        await Promise.resolve();
    });
};

describe('useRunBackup', () => {
    it('queues once, starts polling and refreshes the table props', async () => {
        const { result } = renderHook(() => useRunBackup());

        await act(async () => {
            expect(await result.current.run()).toBe(true);
        });

        expect(requestJson).toHaveBeenCalledWith('/backups/run', {
            method: 'POST',
            signal: expect.any(AbortSignal),
        });
        expect(result.current.status).toBe('queued');
        expect(result.current.polling).toBe(true);
        expect(result.current.pending).toBe(false);
        expect(reloadCurrentPage).toHaveBeenCalledWith({ only: tableProps });
    });

    it('blocks a same-tick duplicate request', async () => {
        let resolveRequest: ((value: unknown) => void) | undefined;
        requestJson.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveRequest = resolve;
                }),
        );
        const { result } = renderHook(() => useRunBackup());

        let first: Promise<boolean> | undefined;
        let second: Promise<boolean> | undefined;
        act(() => {
            first = result.current.run();
            second = result.current.run();
        });

        expect(requestJson).toHaveBeenCalledTimes(1);
        expect(await second).toBe(false);

        await act(async () => {
            resolveRequest?.({ status: 'queued' });
            expect(await first).toBe(true);
        });
    });

    it('clears pending and shows actionable copy when the request times out', async () => {
        requestJson.mockRejectedValueOnce(new DOMException('Timed out', 'TimeoutError'));
        const { result } = renderHook(() => useRunBackup());

        await act(async () => {
            expect(await result.current.run()).toBe(false);
        });

        expect(result.current.pending).toBe(false);
        expect(result.current.status).toBe('idle');
        expect(result.current.error).toBe(
            'The backup request timed out. Check your connection and try again.',
        );
        expect(reloadCurrentPage).not.toHaveBeenCalled();
    });

    it('polls the lightweight status endpoint and refreshes rows after success', async () => {
        const { result } = renderHook(() => useRunBackup());

        await act(async () => {
            await result.current.run();
        });
        requestJson
            .mockResolvedValueOnce({ status: 'running', updated_at: null })
            .mockResolvedValueOnce({ status: 'succeeded', updated_at: null });

        await advancePoll();
        expect(requestJson).toHaveBeenLastCalledWith('/backups/run-status', {
            signal: expect.any(AbortSignal),
        });
        expect(result.current.status).toBe('running');

        await advancePoll();

        expect(result.current.status).toBe('succeeded');
        expect(result.current.polling).toBe(false);
        expect(result.current.error).toBeNull();
        expect(reloadCurrentPage).toHaveBeenLastCalledWith({ only: tableProps });
    });

    it('stops polling and surfaces a failed terminal status', async () => {
        const { result } = renderHook(() => useRunBackup());

        await act(async () => {
            await result.current.run();
        });
        requestJson.mockResolvedValueOnce({ status: 'failed', updated_at: null });
        await advancePoll();

        expect(result.current.status).toBe('failed');
        expect(result.current.polling).toBe(false);
        expect(result.current.error).toContain('The backup failed.');
    });

    it('resumes polling when the page loads during an active run', async () => {
        pageProps.runStatus.status = 'running';
        const { result } = renderHook(() => useRunBackup());

        expect(result.current.status).toBe('running');
        expect(result.current.polling).toBe(true);

        requestJson.mockResolvedValueOnce({ status: 'succeeded', updated_at: null });
        await advancePoll();

        expect(result.current.status).toBe('succeeded');
        expect(result.current.polling).toBe(false);
    });

    it('stops after the polling deadline even if the job never reports terminal state', async () => {
        const { result } = renderHook(() => useRunBackup());

        await act(async () => {
            await result.current.run();
        });
        requestJson.mockResolvedValue({ status: 'running', updated_at: null });
        await advancePoll(30 * 60_000);

        expect(result.current.status).toBe('failed');
        expect(result.current.polling).toBe(false);
        expect(result.current.error).toContain('still queued or running');
    });
});
