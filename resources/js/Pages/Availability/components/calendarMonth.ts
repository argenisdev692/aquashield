/**
 * Month arithmetic for the availability grid.
 *
 * The visible month is a `YYYY-MM-01` string, never a `Date`: the same value
 * goes into the query string, the cache key and the heading, so a browser in a
 * negative-offset timezone cannot shift the window by a day.
 */
import type { AvailabilityDay } from '@/lib/availability';
import { formatIsoDate, parseIsoDate, toIsoDate } from '@/lib/date';

type CalendarDay = AvailabilityDay;

export const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;

export const monthBounds = (monthStart: string): { from: string; to: string } => {
    const anchor = parseIsoDate(monthStart);

    if (anchor === undefined) {
        return { from: monthStart, to: monthStart };
    }

    const first = new Date(anchor.getFullYear(), anchor.getMonth(), 1, 12);
    const last = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0, 12);

    return { from: toIsoDate(first), to: toIsoDate(last) };
};

export const shiftMonth = (monthStart: string, delta: number): string => {
    const anchor = parseIsoDate(monthStart);

    if (anchor === undefined) {
        return monthStart;
    }

    return toIsoDate(new Date(anchor.getFullYear(), anchor.getMonth() + delta, 1, 12));
};

export const monthLabel = (monthStart: string): string =>
    formatIsoDate(monthStart, 'en-US', { month: 'long', year: 'numeric' });

/** Long form used in a cell's accessible name. */
export const spokenDate = (isoDate: string): string =>
    formatIsoDate(isoDate, 'en-US', { weekday: 'long', month: 'long', day: 'numeric' });

export type CalendarWeek = (CalendarDay | null)[];

/**
 * Chunk the resolved month into calendar rows.
 *
 * Each day is placed in its own weekday column, so a missing day (a truncated
 * feed, a partially cached window) leaves one blank cell instead of shifting
 * every later date into the wrong column. The feed is one bounded month, not a
 * paginated list, so this is layout, not server-side filtering.
 */
export const buildWeeks = (days: readonly CalendarDay[]): CalendarWeek[] => {
    const weeks: CalendarWeek[] = [];
    let week: CalendarWeek = [];

    for (const day of days) {
        const column = parseIsoDate(day.date)?.getDay() ?? week.length;

        // A day that belongs to an earlier column means a new row started in
        // the feed; close the current one rather than appending out of order.
        if (column < week.length) {
            weeks.push(padWeek(week));
            week = [];
        }

        while (week.length < column) {
            week.push(null);
        }

        week.push(day);

        if (week.length === 7) {
            weeks.push(week);
            week = [];
        }
    }

    if (week.length > 0) {
        weeks.push(padWeek(week));
    }

    return weeks;
};

const padWeek = (week: CalendarWeek): CalendarWeek => {
    const padded = [...week];

    while (padded.length < 7) {
        padded.push(null);
    }

    return padded;
};

export const sourceLabel = (source: string): string => {
    switch (source) {
        case 'company_rule':
            return 'Weekly hours';
        case 'company_exception':
            return 'Exception';
        default:
            return 'No weekly hours';
    }
};
