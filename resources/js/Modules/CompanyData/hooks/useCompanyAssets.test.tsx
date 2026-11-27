// @vitest-environment jsdom
import { act, cleanup, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { xsrfToken } = vi.hoisted(() => ({
    xsrfToken: vi.fn<() => string>(() => 'xsrf-token'),
}));

vi.mock('@/lib/http', () => ({ xsrfToken }));

import { useCompanyAssets } from './useCompanyAssets';

const initial: Modules.CompanyData.Data.CompanyAssetsDto = {
    logo_url: null,
    logo_white_url: null,
    mark_url: null,
    signature_url: null,
};

const updated: Modules.CompanyData.Data.CompanyAssetsDto = {
    logo_url: '/signed/logo.webp',
    logo_white_url: null,
    mark_url: null,
    signature_url: null,
};

const file = () => new File(['image'], 'logo.png', { type: 'image/png' });
const response = (payload: unknown, status = 200): Response =>
    ({
        ok: status >= 200 && status < 300,
        status,
        json: async () => payload,
    }) as Response;

const fetchMock = vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>();

beforeEach(() => {
    vi.useFakeTimers();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('useCompanyAssets request lifecycle', () => {
    it('uploads with CSRF and an abort signal, then publishes validated server state', async () => {
        fetchMock.mockResolvedValueOnce(response(updated));
        const { result } = renderHook(() => useCompanyAssets(initial));

        await act(async () => {
            expect(await result.current.upload('logo', file())).toBe(true);
        });

        expect(fetchMock).toHaveBeenCalledWith(
            '/company-data/assets/logo',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'xsrf-token' }),
                signal: expect.any(AbortSignal),
            }),
        );
        expect(result.current.assets).toEqual(updated);
        expect(result.current.pending).toBeNull();
    });

    it('blocks a same-tick duplicate mutation', async () => {
        let resolveFetch: ((value: Response) => void) | undefined;
        fetchMock.mockImplementationOnce(
            () =>
                new Promise<Response>((resolve) => {
                    resolveFetch = resolve;
                }),
        );
        const { result } = renderHook(() => useCompanyAssets(initial));

        let first: Promise<boolean> | undefined;
        let second: Promise<boolean> | undefined;
        act(() => {
            first = result.current.upload('logo', file());
            second = result.current.remove('logo');
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(await second).toBe(false);

        await act(async () => {
            resolveFetch?.(response(updated));
            expect(await first).toBe(true);
        });
    });

    it('clears pending and reports a timeout', async () => {
        fetchMock.mockImplementationOnce(
            (_input, init) =>
                new Promise<Response>((_resolve, reject) => {
                    init?.signal?.addEventListener('abort', () => {
                        reject(new DOMException('Aborted', 'AbortError'));
                    });
                }),
        );
        const { result } = renderHook(() => useCompanyAssets(initial));

        let request: Promise<boolean> | undefined;
        act(() => {
            request = result.current.upload('logo', file());
        });

        expect(result.current.pending).toBe('logo');

        await act(async () => {
            vi.advanceTimersByTime(30_000);
            expect(await request).toBe(false);
        });

        expect(result.current.pending).toBeNull();
        expect(result.current.error).toMatch(/timed out/i);
    });

    it('aborts an in-flight mutation on unmount', async () => {
        let signal: AbortSignal | null | undefined;
        fetchMock.mockImplementationOnce(
            (_input, init) =>
                new Promise<Response>((_resolve, reject) => {
                    signal = init?.signal;
                    init?.signal?.addEventListener('abort', () => {
                        reject(new DOMException('Aborted', 'AbortError'));
                    });
                }),
        );
        const { result, unmount } = renderHook(() => useCompanyAssets(initial));

        let request: Promise<boolean> | undefined;
        act(() => {
            request = result.current.remove('logo');
        });
        act(() => unmount());

        expect(signal?.aborted).toBe(true);
        await act(async () => {
            expect(await request).toBe(false);
        });
    });

    it('surfaces the session-expiry message without exposing a raw response body', async () => {
        fetchMock.mockResolvedValueOnce(response({ message: 'internal detail' }, 419));
        const { result } = renderHook(() => useCompanyAssets(initial));

        await act(async () => {
            expect(await result.current.remove('logo')).toBe(false);
        });

        expect(result.current.error).toMatch(/session expired/i);
        expect(result.current.error).not.toContain('internal detail');
    });
});
