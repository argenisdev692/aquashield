/**
 * Presentation helpers for the resolved availability feed.
 *
 * Pure functions: no React, no page ownership. They exist because the month
 * grid (a page-private component) and the appointment form's day notice (a
 * module component) render the same "08:00–12:00, 13:00–17:00" string, and
 * neither layer is allowed to import the other.
 */

/**
 * One resolved day of `/availability/calendar`.
 *
 * The endpoint answers `AvailabilityCalendarData`, whose payload is
 * `AvailabilityDayData` — NOT `CalendarDayData`, which is the appointment feed
 * and carries an extra `appointments` array. Typing this as `CalendarDayData`
 * made `day.appointments` compile while being `undefined` at runtime.
 */
export type AvailabilityDay = Modules.LeadAppointment.Application.Dtos.AvailabilityDayData;

/**
 * One opening window. Indexed off the generated DTO instead of being
 * hand-written: the shape is the backend's, and a hand-written mirror is how
 * silent drift gets in.
 */
export type AvailabilityWindow = AvailabilityDay['windows'][number];

/** One line of opening hours: `08:00–12:00, 13:00–17:00`, empty when closed. */
export const formatWindows = (windows: readonly AvailabilityWindow[]): string =>
    windows.map((window) => `${window.start}–${window.end}`).join(', ');
