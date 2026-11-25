/**
 * Pure mapping from the server's slim calendar event to FullCalendar's event
 * input. Kept outside the component so the timezone and status rules can be
 * tested without mounting the calendar.
 */
import type { EventInput } from '@fullcalendar/react';
import type { CalendarEvent } from '@/Modules/LeadAppointment/hooks/useCalendarEvents';

export type CalendarTone = 'normal' | 'completed' | 'cancelled';

export const toneForStatus = (status: string): CalendarTone => {
    if (status === 'completed') {
        return 'completed';
    }

    if (status === 'cancelled' || status === 'no_show') {
        return 'cancelled';
    }

    return 'normal';
};

const statusLabel = (status: string): string => {
    switch (status) {
        case 'cancelled':
            return 'Cancelled';
        case 'no_show':
            return 'No show';
        case 'completed':
            return 'Completed';
        default:
            return '';
    }
};

/**
 * FullCalendar treats an ISO string without an offset as a wall-clock value in
 * the calendar timezone. `starts_at_local` already represents the property's
 * local time, so dropping the offset keeps 10:00 Eastern at 10:00 for every
 * viewer instead of silently shifting it to the browser's zone.
 */
export const wallClockStart = (value: string): string => value.slice(0, 19);

export const toCalendarEvent = (event: CalendarEvent): EventInput => {
    const tone = toneForStatus(event.status);
    const suffix = statusLabel(event.status);

    return {
        id: event.uuid,
        title: suffix === '' ? event.customer_name : `${event.customer_name} · ${suffix}`,
        start: wallClockStart(event.starts_at_local),
        color: `var(--aq-calendar-${tone}-bg)`,
        contrastColor: `var(--aq-calendar-${tone}-ink)`,
    };
};
