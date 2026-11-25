import { describe, expect, it } from 'vitest';
import type { CalendarEvent } from '@/Modules/LeadAppointment/hooks/useCalendarEvents';
import { toCalendarEvent, toneForStatus, wallClockStart } from './calendarEvents';

const event = (overrides: Partial<CalendarEvent> = {}): CalendarEvent => ({
    uuid: 'appointment-1',
    lead_uuid: 'lead-1',
    customer_name: 'Ada Lovelace',
    starts_at_local: '2026-11-24T10:00:00-06:00',
    timezone: 'America/Chicago',
    status: 'scheduled',
    kind: 'inspection',
    region: 'houston',
    ...overrides,
});

describe('calendar event projection', () => {
    it('keeps the property wall-clock time instead of shifting it', () => {
        const mapped = toCalendarEvent(
            event({
                starts_at_local: '2026-11-24T10:00:00-05:00',
                timezone: 'America/New_York',
                region: 'florida',
            }),
        );

        expect(mapped.start).toBe('2026-11-24T10:00:00');
        expect(wallClockStart('2026-11-24T10:00:00-05:00')).toBe('2026-11-24T10:00:00');
    });

    it('carries the cancelled state in the title and the tone', () => {
        const mapped = toCalendarEvent(event({ status: 'cancelled' }));

        expect(mapped.title).toBe('Ada Lovelace · Cancelled');
        expect(mapped.color).toBe('var(--aq-calendar-cancelled-bg)');
        expect(mapped.contrastColor).toBe('var(--aq-calendar-cancelled-ink)');
    });

    it('renders a no-show as cancelled with its own label', () => {
        expect(toneForStatus('no_show')).toBe('cancelled');
        expect(toCalendarEvent(event({ status: 'no_show' })).title).toBe('Ada Lovelace · No show');
    });

    it('marks a completed visit with the completed tone and a text label', () => {
        const mapped = toCalendarEvent(event({ status: 'completed' }));

        expect(mapped.title).toBe('Ada Lovelace · Completed');
        expect(mapped.color).toBe('var(--aq-calendar-completed-bg)');
    });

    it('leaves active visits normal', () => {
        const mapped = toCalendarEvent(event());

        expect(mapped.title).toBe('Ada Lovelace');
        expect(mapped.color).toBe('var(--aq-calendar-normal-bg)');
    });
});
