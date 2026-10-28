/**
 * Display helpers private to the Users screens.
 *
 * `formatCalendarDate` is deliberately not `new Date('YYYY-MM-DD')`: parsing a
 * date-only string as UTC shifts the day for every negative-offset timezone.
 */

export const initials = (name: string): string => {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || '?'
    );
};

export const formatDateTime = (value: string | null): string => {
    if (value === null || value === '') {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

export const formatCalendarDate = (value: string | null): string => {
    if (value === null || value === '') {
        return '—';
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(year, month - 1, day),
    );
};
