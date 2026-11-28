/**
 * Calendar-date helpers.
 *
 * Laravel's `date` rule and an Eloquent `date` cast both speak ISO
 * `YYYY-MM-DD`. A JavaScript `Date` always carries a time and a timezone, so
 * `new Date('2026-03-01')` parsed as UTC can render as 28 February in a
 * negative-offset zone. These helpers keep the wire value timezone-free and
 * only build a local `Date` for a calendar widget that needs one.
 */

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

/**
 * One `Intl` instance per locale+options pair.
 *
 * A formatter is built once per column cell today (`formatDateTime` runs for
 * every row on every render). The option sets in this app are a handful, so a
 * module-level cache removes the allocation without an unbounded map.
 */
const formatters = new Map<string, Intl.DateTimeFormat>();

const formatterKey = (locale: string, options: Intl.DateTimeFormatOptions): string =>
    `${locale}|${Object.entries(options)
        .map(([key, value]) => `${key}:${String(value)}`)
        .sort()
        .join(',')}`;

const dateTimeFormatter = (
    locale: string,
    options: Intl.DateTimeFormatOptions,
): Intl.DateTimeFormat => {
    const key = formatterKey(locale, options);
    const cached = formatters.get(key);

    if (cached !== undefined) {
        return cached;
    }

    const formatter = new Intl.DateTimeFormat(locale, options);
    formatters.set(key, formatter);

    return formatter;
};

/** Parse ISO `YYYY-MM-DD` into a local noon `Date`, or `undefined` if invalid. */
export function parseIsoDate(value: string | null | undefined): Date | undefined {
    if (!value) {
        return undefined;
    }

    const match = ISO_DATE.exec(value);

    if (!match) {
        return undefined;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day, 12);

    // Reject overflow such as 2026-02-31: constructing a Date rolls it into
    // March, so comparing the parts back is the only reliable check.
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return undefined;
    }

    return date;
}

/**
 * True for a calendar-valid `YYYY-MM-DD` (`2026-02-31` is rejected, not rolled
 * into March). Use this instead of a second local copy of the date regex.
 */
export function isIsoDate(value: string): boolean {
    return parseIsoDate(value) !== undefined;
}

/** Format a local `Date` as ISO `YYYY-MM-DD` without a UTC conversion. */
export function toIsoDate(date: Date): string {
    const year = String(date.getFullYear()).padStart(4, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** Format an ISO date for display, or return an empty string when invalid. */
export function formatIsoDate(
    value: string | null | undefined,
    locale = 'en-US',
    options: Intl.DateTimeFormatOptions = { dateStyle: 'long' },
): string {
    const date = parseIsoDate(value);

    return date ? dateTimeFormatter(locale, options).format(date) : '';
}

/**
 * Format an ISO date-time for display in the viewer's locale and timezone.
 *
 * A raw `2026-10-28T15:29:48+00:00` costs ~200px of column width, is hard to
 * scan and hides its own meaning in a timestamp nobody reads. An unparseable
 * value is passed through unchanged rather than blanked: showing the source
 * value is a visible data problem, an empty cell is an invisible one.
 */
export function formatDateTime(
    value: string | null | undefined,
    locale = 'en-US',
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },
): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : dateTimeFormatter(locale, options).format(date);
}

/**
 * Format a server-localized ISO date-time as the wall clock it encodes.
 *
 * The server sends `inspection_at` already converted to the appointment's
 * timezone, with that zone's offset (for example `10:00:00-05:00`). Parsing
 * the whole string in the browser converts the instant into the viewer's
 * zone, which is the wrong hour for a board where the visit happens at the
 * property. Dropping the offset keeps 10:00 at 10:00; the offset is metadata,
 * not a conversion instruction.
 */
export function formatWallClockDateTime(
    value: string | null | undefined,
    locale = 'en-US',
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' },
): string {
    if (!value) {
        return '';
    }

    const wallClock = /^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/.exec(value)?.[1] ?? value;

    return formatDateTime(wallClock, locale, options);
}

/** Format an operational timestamp in the explicit UTC contract. */
export function formatUtcDateTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const formatted = formatDateTime(value, 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'UTC',
    });

    return formatted === value ? formatted : `${formatted} UTC`;
}

/**
 * Today's calendar date in the viewer's local zone as `YYYY-MM-DD`.
 *
 * `toISOString().slice(0, 10)` is the UTC day: at 20:00 in UTC-5 it already
 * returns tomorrow, which made a date picker accept a birthday the schema then
 * rejected. Calendar inputs and Zod refinements must share this one answer.
 */
export function todayLocalIso(): string {
    return toIsoDate(new Date());
}

/**
 * Today's calendar date in the given zone as `YYYY-MM-DD`.
 *
 * The appointment wire value is a wall-clock date parsed in the appointment's
 * region timezone, so a browser-local `toISOString()` can be a day ahead or
 * behind at the boundary. `formatToParts` keeps the year/month/day assignment
 * explicit instead of relying on a locale that formats with slashes.
 */
export function todayIso(timeZone?: string): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());

    const value = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((part) => part.type === type)?.value ?? '';

    return `${value('year')}-${value('month')}-${value('day')}`;
}
