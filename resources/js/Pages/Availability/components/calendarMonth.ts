/**
 * Month arithmetic for the availability grid.
 *
 * The visible month is a `YYYY-MM-01` string, never a `Date`: the same value
 * goes into the query string, the cache key and the heading, so a browser in a
 * negative-offset timezone cannot shift the window by a day.
 */
import { formatIsoDate, parseIsoDate, toIsoDate } from '@/lib/date';

type CalendarDay = Modules.LeadAppointment.Application.Dtos.CalendarDayData;

export const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;

export const monthStartOf = (isoDate: string): string => `${isoDate.slice(0, 7)}-01`;

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
 * Chunk the resolved month into calendar rows. The feed is one bounded month
 * (not a paginated list), so this is layout, not server-side filtering.
 */
export const buildWeeks = (days: readonly CalendarDay[]): CalendarWeek[] => {
    if (days.length === 0) {
        return [];
    }

    const firstDay = parseIsoDate(days[0].date);
    const offset = firstDay === undefined ? 0 : firstDay.getDay();
    const weeks: CalendarWeek[] = [];
    let week: CalendarWeek = Array.from({ length: offset }, () => null);

    for (const day of days) {
        week.push(day);

        if (week.length === 7) {
            weeks.push(week);
            week = [];
        }
    }

    if (week.length > 0) {
        while (week.length < 7) {
            week.push(null);
        }

        weeks.push(week);
    }

    return weeks;
};

export const windowsLabel = (day: CalendarDay): string =>
    day.windows.map((window) => `${window.start}–${window.end}`).join(', ');

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
