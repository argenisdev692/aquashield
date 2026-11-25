import { describe, expect, it } from 'vitest';
import type { AvailabilityDay } from '@/lib/availability';
import {
    buildWeeks,
    monthBounds,
    monthLabel,
    shiftMonth,
    sourceLabel,
    spokenDate,
} from './calendarMonth';

const day = (date: string): AvailabilityDay => ({
    date,
    is_open: true,
    source: 'company_rule',
    reason: null,
    windows: [{ start: '08:00', end: '17:00' }],
});

/** A contiguous feed starting at `from`, the way the resolver answers a month. */
const daysIn = (from: string, count: number): AvailabilityDay[] => {
    const [year, month, start] = from.split('-').map(Number) as [number, number, number];

    return Array.from({ length: count }, (_, index) => {
        const date = new Date(year, month - 1, start + index, 12);
        const iso = [
            String(date.getFullYear()).padStart(4, '0'),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');

        return day(iso);
    });
};

describe('monthBounds', () => {
    it('spans the whole month, including a leap February', () => {
        expect(monthBounds('2026-11-01')).toEqual({ from: '2026-11-01', to: '2026-11-30' });
        expect(monthBounds('2028-02-01')).toEqual({ from: '2028-02-01', to: '2028-02-29' });
    });

    it('falls back to the input when the month is not a date', () => {
        expect(monthBounds('not-a-date')).toEqual({ from: 'not-a-date', to: 'not-a-date' });
    });
});

describe('shiftMonth', () => {
    it('crosses the year boundary in both directions', () => {
        expect(shiftMonth('2026-12-01', 1)).toBe('2027-01-01');
        expect(shiftMonth('2026-01-01', -1)).toBe('2025-12-01');
    });
});

describe('buildWeeks', () => {
    it('pads the first week when the month starts mid-week', () => {
        // 2026-04-01 is a Wednesday.
        const weeks = buildWeeks(daysIn('2026-04-01', 30));

        expect(weeks[0].slice(0, 3)).toEqual([null, null, null]);
        expect(weeks[0][3]?.date).toBe('2026-04-01');
        expect(weeks.every((week) => week.length === 7)).toBe(true);
    });

    it('keeps a missing day in its own column instead of shifting later days', () => {
        // 2026-11-01 is a Sunday; drop the Wednesday.
        const days = daysIn('2026-11-01', 30).filter((entry) => entry.date !== '2026-11-04');
        const weeks = buildWeeks(days);

        expect(weeks[0][3]).toBeNull();
        expect(weeks[0][4]?.date).toBe('2026-11-05');
    });

    it('returns no rows for an empty feed', () => {
        expect(buildWeeks([])).toEqual([]);
    });
});

describe('labels', () => {
    it('names the source that decided the day', () => {
        expect(sourceLabel('company_rule')).toBe('Weekly hours');
        expect(sourceLabel('company_exception')).toBe('Exception');
        expect(sourceLabel('anything_else')).toBe('No weekly hours');
    });

    it('formats the month and the spoken date without a UTC shift', () => {
        expect(monthLabel('2026-11-01')).toBe('November 2026');
        expect(spokenDate('2026-11-01')).toBe('Sunday, November 1');
    });
});
