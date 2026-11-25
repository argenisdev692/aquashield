// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, renderHook, waitFor } from '@testing-library/react';

/**
 * The bulk endpoints answer with a summary, and that summary is the result.
 * A fallback that assumed `updated: rows.length` reported success for work the
 * server never confirmed, which is the one thing this hook exists to avoid:
 * "no control pretends a protected row was changed".
 */
const { reload, requestJson } = vi.hoisted(() => ({
    reload: vi.fn<() => void>(),
    requestJson: vi.fn<() => Promise<unknown>>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { reload },
}));

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/http')>();

    return { ...actual, requestJson };
});

import { useUserBulkActions } from './useUserBulkActions';

type Feedback = { tone: 'success' | 'error'; message: string };

const runDelete = async (payload: unknown): Promise<Feedback> => {
    requestJson.mockResolvedValueOnce(payload);

    const onFeedback = vi.fn<(feedback: Feedback) => void>();

    const { result } = renderHook(() => useUserBulkActions({ onFeedback }));

    result.current.deleteRows([{ uuid: 'a' }, { uuid: 'b' }]);

    await waitFor(() => expect(onFeedback).toHaveBeenCalledTimes(1));

    return onFeedback.mock.calls[0][0];
};

beforeEach(() => {
    requestJson.mockReset();
    reload.mockReset();
});

afterEach(() => {
    cleanup();
});

describe('useUserBulkActions', () => {
    it('reports an error instead of inventing a success for an unknown body', async () => {
        const feedback = await runDelete({ ok: true });

        expect(feedback.tone).toBe('error');
        expect(feedback.message).toMatch(/did not confirm/i);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('reports the confirmed summary', async () => {
        const feedback = await runDelete({ updated: 2, skipped: [] });

        expect(feedback).toEqual({ tone: 'success', message: '2 accounts deleted.' });
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('reports a partial result with the skip reason', async () => {
        const feedback = await runDelete({
            updated: 1,
            skipped: [{ uuid: 'b', reason: 'Protected account' }],
        });

        expect(feedback.tone).toBe('success');
        expect(feedback.message).toContain('1 account deleted.');
        expect(feedback.message).toContain('1 skipped — Protected account');
    });

    it('reports a summary with nothing updated as a failure', async () => {
        const feedback = await runDelete({
            updated: 0,
            skipped: [{ uuid: 'a', reason: 'Protected account' }],
        });

        expect(feedback.tone).toBe('error');
        expect(feedback.message).toContain('No accounts were deleted.');
    });

    it('does not call the endpoint for an empty selection', async () => {
        const onFeedback = vi.fn<(feedback: Feedback) => void>();

        const { result } = renderHook(() => useUserBulkActions({ onFeedback }));

        result.current.deleteRows([]);

        await Promise.resolve();

        expect(requestJson).not.toHaveBeenCalled();
        expect(onFeedback).not.toHaveBeenCalled();
    });
});
