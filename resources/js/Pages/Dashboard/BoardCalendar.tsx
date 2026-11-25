/**
 * Dashboard/BoardCalendar — the month grid for the pipeline section.
 *
 * The page owns the visible range and the event query; this component owns
 * FullCalendar's view state and reports every range change back. Events are
 * mapped from the slim calendar projection (customer, local start, status), so
 * the grid never needs notes, addresses or contact details.
 */
import { useMemo } from 'react';
import { Loader2 } from 'lucide-react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/react/daygrid';
import interactionPlugin from '@fullcalendar/react/interaction';
import formaTheme from '@fullcalendar/react/themes/forma';
import type {
    CalendarOptions,
    DateClickInfo,
    DatesSetInfo,
    EventClickInfo,
    EventInput,
} from '@fullcalendar/react';
import type { CalendarEvent } from '@/Modules/LeadAppointment/hooks/useCalendarEvents';
import { toIsoDate } from '@/lib/date';
import { toCalendarEvent } from './calendarEvents';

import '@fullcalendar/react/skeleton.css';
import '@fullcalendar/react/themes/forma/theme.css';
import '@fullcalendar/react/themes/forma/palettes/blue.css';
import './calendar-palette.css';

interface BoardCalendarProps {
    events: CalendarEvent[];
    colorScheme: 'light' | 'dark';
    /** True while the visible range is being fetched. */
    isFetching?: boolean;
    /** Appointment id — the calendar is a projection of the board. */
    onSelect?: (id: string) => void;
    /** A day click when the viewer may schedule a visit. */
    onDateClick?: (date: string) => void;
    /** Reports the visible grid range as inclusive ISO dates. */
    onRangeChange?: (from: string, to: string) => void;
}

export default function BoardCalendar({
    events,
    colorScheme,
    isFetching = false,
    onSelect,
    onDateClick,
    onRangeChange,
}: BoardCalendarProps) {
    /*
     * FullCalendar treats `events` as an external input and reconciles the
     * event source when the reference changes. This memo is an identity
     * contract for a third-party API, not a render-performance habit.
     */
    const calendarEvents = useMemo<EventInput[]>(() => events.map(toCalendarEvent), [events]);

    const handleEventClick = (info: EventClickInfo): void => {
        info.jsEvent.preventDefault();

        onSelect?.(info.event.id);
    };

    const handleDateClick = (info: DateClickInfo): void => {
        onDateClick?.(toIsoDate(info.date));
    };

    const handleDatesSet = (info: DatesSetInfo): void => {
        // FullCalendar's end is exclusive; the API expects an inclusive date.
        const end = new Date(info.end.getTime() - 86_400_000);
        onRangeChange?.(toIsoDate(info.start), toIsoDate(end));
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
        events: calendarEvents,
        datesSet: handleDatesSet,
        eventClick: handleEventClick,
        ...(onDateClick === undefined ? {} : { dateClick: handleDateClick }),
    };

    return (
        <div className="relative">
            <p className="px-[1.125rem] pt-3 text-xs text-surface-500 dark:text-surface-300">
                Times are shown in each appointment&apos;s local time. Completed and cancelled
                visits carry their state on the event.
            </p>

            <FullCalendar {...calendarOptions} />

            {isFetching ? (
                <span
                    role="status"
                    className="pointer-events-none absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-surface-0/90 px-2.5 py-1 text-xs font-medium text-surface-600 shadow-sm dark:bg-surface-800/90 dark:text-surface-200"
                >
                    <Loader2
                        className="size-3 animate-spin motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    Loading
                </span>
            ) : null}
        </div>
    );
}
