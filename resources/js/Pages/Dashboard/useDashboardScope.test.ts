// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { dashboardUrl, mergeScopeFilters, useDashboardScope } from './useDashboardScope';

describe('mergeScopeFilters', () => {
    it('merges a patch and deletes blank values', () => {
        expect(mergeScopeFilters({ region: 'dallas', source: 'meta' }, { region: null })).toEqual({
            source: 'meta',
        });
    });
});

describe('dashboardUrl', () => {
    it('omits the default range and serialises filters', () => {
        expect(dashboardUrl('last_30_days', { region: 'dallas' })).toBe(
            '/dashboard?filter%5Bregion%5D=dallas',
        );
        expect(dashboardUrl('last_90_days', {})).toBe('/dashboard?range=last_90_days');
    });
});

describe('useDashboardScope', () => {
    it('composes a filter and a period change while the first visit is in flight', () => {
        const visits: Array<{
            url: string;
            options: { onFinish: () => void; onCancel: () => void };
        }> = [];
        const visit = vi.fn<
            (url: string, options: { onFinish: () => void; onCancel: () => void }) => void
        >((url, options) => {
            visits.push({ url, options });
        });

        const { result } = renderHook(() =>
            useDashboardScope({
                url: '/dashboard',
                range: 'last_30_days',
                visit,
            }),
        );

        act(() => {
            result.current.commit('last_30_days', { region: 'dallas' });
        });
        act(() => {
            result.current.commit('last_90_days', {});
        });

        expect(visits).toHaveLength(2);

        const second = new URL(visits[1]?.url ?? '', 'https://example.test').searchParams;

        expect(second.get('range')).toBe('last_90_days');
        expect(second.get('filter[region]')).toBe('dallas');
    });

    it('keeps the pending scope until every visit settles', () => {
        const visits: Array<{
            url: string;
            options: { onFinish: () => void; onCancel: () => void };
        }> = [];
        const visit = vi.fn<
            (url: string, options: { onFinish: () => void; onCancel: () => void }) => void
        >((url, options) => {
            visits.push({ url, options });
        });

        const { result } = renderHook(() =>
            useDashboardScope({
                url: '/dashboard',
                range: 'last_30_days',
                visit,
            }),
        );

        act(() => {
            result.current.commit('last_30_days', { region: 'dallas' });
        });

        expect(result.current.pending).toBe(true);
        expect(result.current.filters.region).toBe('dallas');

        act(() => {
            visits[0]?.options.onCancel();
        });

        expect(result.current.pending).toBe(false);
        expect(result.current.filters.region).toBeUndefined();
    });
});
