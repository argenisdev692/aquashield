<script lang="ts">
/**
 * The contract the dashboard hands to the field calendar. Declared in a plain
 * `<script>` block because `script setup` cannot export types, and the page
 * needs the same shape it produces.
 */
export type AppointmentTone = 'normal' | 'high' | 'emergency';

export interface CalendarAppointment {
    id: string;
    title: string;
    start: Date;
    tone: AppointmentTone;
}
</script>

<script setup lang="ts">
/**
 * Field calendar for the pipeline section — the second tab of the board.
 *
 * A month grid over the appointments the board already owns, so this view adds
 * no data source and no fetch: the page passes `appointments`, the calendar is
 * only responsible for painting a tone. Clicking an event emits its id and the
 * page opens the same Detail drawer a Kanban card opens.
 *
 * FullCalendar v7 facts that shape this file:
 * - There is no `@fullcalendar/daygrid` package any more; views are subpaths
 *   of the Vue connector (`@fullcalendar/vue3/daygrid`).
 * - CSS is no longer bundled: skeleton + theme + palette, in that order.
 * - Themes are plugins, not a `themeSystem` string.
 * - Dark mode is the `colorScheme` option (emitted as `data-color-scheme`);
 *   the stock palette is re-pointed at the design tokens in
 *   `calendar-palette.css`, so the grid follows `.dark` like every other
 *   surface.
 * - `eventBackgroundColor` / `eventBorderColor` / `eventClassNames` are gone;
 *   per-event `color` + `contrastColor` are the v7 names.
 *
 * `Dashboard.vue` imports this lazily: the calendar chunk only arrives when
 * the Calendar tab is opened, so the KPIs never wait for it.
 */
import { computed } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/vue3/daygrid';
import formaTheme from '@fullcalendar/vue3/themes/forma';
import type { CalendarOptions, EventClickInfo, EventInput } from '@fullcalendar/vue3';

import '@fullcalendar/vue3/skeleton.css';
import '@fullcalendar/vue3/themes/forma/theme.css';
import '@fullcalendar/vue3/themes/forma/palettes/blue.css';
import './calendar-palette.css';

const props = defineProps<{
    appointments: readonly CalendarAppointment[];
    /** Mirrors the app theme (`.dark` is the only source); FullCalendar only
     * understands `data-color-scheme`. */
    colorScheme: 'light' | 'dark';
}>();

const emit = defineEmits<{ select: [id: string] }>();

/**
 * Fill and the text that sits on it, both read from the token layer — the
 * event colour *is* the priority vocabulary, so it comes from the same tokens
 * the Kanban card border uses.
 */
const TONE_STYLE: Record<AppointmentTone, { color: string; contrastColor: string }> = {
    normal: {
        color: 'var(--p-primary-500)',
        contrastColor: 'var(--p-primary-contrast-color)',
    },
    high: {
        color: 'var(--color-aq-warning)',
        contrastColor: 'var(--p-primary-contrast-color)',
    },
    emergency: {
        color: 'var(--color-aq-danger)',
        contrastColor: 'var(--p-surface-0)',
    },
};

const events = computed<EventInput[]>(() =>
    props.appointments.map((appointment) => ({
        id: appointment.id,
        title: appointment.title,
        start: appointment.start,
        ...TONE_STYLE[appointment.tone],
    })),
);

const onEventClick = (info: EventClickInfo): void => {
    info.jsEvent.preventDefault();

    emit('select', info.event.id);
};

const options = computed<CalendarOptions>(() => ({
    plugins: [formaTheme, dayGridPlugin],
    initialView: 'dayGridMonth',
    colorScheme: props.colorScheme,
    // v7 renders no toolbar by default; the month grid needs its navigation.
    headerToolbar: { start: 'prev,next today', center: 'title', end: '' },
    height: 'auto',
    dayMaxEvents: 3,
    firstDay: 0,
    events: events.value,
    eventClick: onEventClick,
}));
</script>

<template>
    <FullCalendar :options="options" />
</template>
