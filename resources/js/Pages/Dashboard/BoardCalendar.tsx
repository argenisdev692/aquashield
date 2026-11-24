/**
 * Dashboard/BoardCalendar — field calendar for the pipeline section.
 * React port of the Vue/FullCalendar component.
 */
import { useMemo } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/react/daygrid';
import interactionPlugin from '@fullcalendar/react/interaction';
import formaTheme from '@fullcalendar/react/themes/forma';
import type {
    CalendarOptions,
    DateClickInfo,
    EventClickInfo,
    EventInput,
} from '@fullcalendar/react';
import { toIsoDate } from '@/lib/date';

import '@fullcalendar/react/skeleton.css';
import '@fullcalendar/react/themes/forma/theme.css';
import '@fullcalendar/react/themes/forma/palettes/blue.css';
import './calendar-palette.css';

export type AppointmentTone = 'normal' | 'high' | 'emergency';

export interface CalendarAppointment {
    id: string;
    title: string;
    start: Date;
    tone: AppointmentTone;
}

interface BoardCalendarProps {
    appointments: CalendarAppointment[];
    colorScheme: 'light' | 'dark';
    /** Appointment id — the calendar is a projection of the board. */
    onSelect?: (id: string) => void;
    /** A day click when the viewer may schedule a visit. */
    onDateClick?: (date: string) => void;
}

/**
 * Fill and the text that sits on it, both read from the token layer — the
 * event colour *is* the priority vocabulary, so it comes from the same tokens
 * the Kanban card border uses.
 */
const TONE_STYLE: Record<AppointmentTone, { color: string; contrastColor: string }> = {
    normal: { color: 'var(--p-primary-500)', contrastColor: 'var(--p-primary-contrast-color)' },
    high: { color: 'var(--color-aq-warning)', contrastColor: 'var(--p-primary-contrast-color)' },
    emergency: { color: 'var(--color-aq-danger)', contrastColor: 'var(--p-surface-0)' },
};

export default function BoardCalendar({
    appointments,
    colorScheme,
    onSelect,
    onDateClick,
}: BoardCalendarProps) {
    /*
     * FullCalendar treats `events` as an external input and reconciles the
     * event source when the reference changes. This memo is an identity
     * contract for a third-party API, not a render-performance habit.
     */
    const events = useMemo<EventInput[]>(
        () =>
            appointments.map((appointment) => ({
                id: appointment.id,
                title: appointment.title,
                start: appointment.start,
                ...TONE_STYLE[appointment.tone],
            })),
        [appointments],
    );

    const handleEventClick = (info: EventClickInfo): void => {
        info.jsEvent.preventDefault();

        onSelect?.(info.event.id);
    };

    const handleDateClick = (info: DateClickInfo): void => {
        onDateClick?.(toIsoDate(info.date));
    };

    const calendarOptions: CalendarOptions = {
        plugins: [dayGridPlugin, interactionPlugin, formaTheme],
        initialView: 'dayGridMonth',
        colorScheme,
        // v7 renders no toolbar by default; the month grid needs its navigation.
        headerToolbar: { start: 'prev,next today', center: 'title', end: '' },
        height: 'auto',
        dayMaxEvents: 3,
        firstDay: 0,
        events,
        eventClick: handleEventClick,
        ...(onDateClick === undefined ? {} : { dateClick: handleDateClick, selectable: true }),
    };

    return <FullCalendar {...calendarOptions} />;
}
