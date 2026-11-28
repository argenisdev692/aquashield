import { describe, expect, it } from 'vitest';
import { periodDelta } from './dashboardMetrics';

describe('periodDelta', () => {
    it('keeps two empty periods neutral', () => {
        expect(periodDelta({ current: 0, previous: 0, series: [0, 0] })).toEqual({
            trend: 'flat',
            label: 'No activity in this period',
        });
    });

    it('reports an absolute change when there is no previous base', () => {
        expect(periodDelta({ current: 3, previous: 0, series: [0, 3] })).toEqual({
            trend: 'up',
            label: '+3 vs. previous period',
        });
    });

    it('reports a percentage when there is a base', () => {
        expect(periodDelta({ current: 12, previous: 10, series: [0, 12] })).toEqual({
            trend: 'up',
            label: '+20% vs. previous period',
        });
    });

    it('never hides a remaining count behind a rounded -100%', () => {
        expect(periodDelta({ current: 1, previous: 300, series: [0, 1] })).toEqual({
            trend: 'down',
            label: '-299 vs. previous period',
        });
    });

    it('falls back to the absolute change when the percentage rounds to zero', () => {
        expect(periodDelta({ current: 301, previous: 300, series: [0, 301] })).toEqual({
            trend: 'up',
            label: '+1 vs. previous period',
        });
    });

    it('marks an unchanged non-zero period as flat', () => {
        expect(periodDelta({ current: 5, previous: 5, series: [0, 5] })).toEqual({
            trend: 'flat',
            label: 'same as previous period',
        });
    });
});
