/**
 * Dashboard appointment drawer content.
 *
 * Renders the slim calendar event immediately, then replaces the body with the
 * full appointment file when its detail query lands. The page keeps the Sheet
 * shell; this component owns only the appointment copy and links.
 */
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import { formatDateTime } from '@/lib/date';
import { humanize } from '@/lib/utils';
import type { CalendarEvent } from '@/Modules/LeadAppointment/hooks/useCalendarEvents';
import type { AppointmentDetail } from '@/Modules/LeadAppointment/hooks/useAppointmentDetail';

interface AppointmentDetailSheetProps {
    event: CalendarEvent;
    appointment: AppointmentDetail | null;
    isPending: boolean;
    isError: boolean;
    canViewAppointment: boolean;
    canViewLead: boolean;
}

export default function AppointmentDetailSheet({
    event,
    appointment,
    isPending,
    isError,
    canViewAppointment,
    canViewLead,
}: AppointmentDetailSheetProps) {
    return (
        <>
            <SheetHeader>
                <SheetTitle className="text-lg font-bold text-surface-900 dark:text-surface-0">
                    {appointment?.customer_name ?? event.customer_name}
                </SheetTitle>
                <SheetDescription>
                    {humanize(appointment?.kind ?? event.kind)} ·{' '}
                    {humanize(appointment?.status ?? event.status)}
                </SheetDescription>
            </SheetHeader>

            <div className="mt-4 flex flex-col gap-3 px-4 text-sm text-surface-600 dark:text-surface-300">
                {canViewAppointment && isPending ? (
                    <div className="flex flex-col gap-2" aria-busy="true">
                        <Skeleton className="h-5 w-3/4" />
                        <Skeleton className="h-5 w-1/2" />
                        <Skeleton className="h-16 w-full" />
                    </div>
                ) : appointment !== null ? (
                    <>
                        <p>
                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                When:
                            </span>{' '}
                            {formatDateTime(appointment.starts_at_local, 'en-US', {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                                timeZone: appointment.timezone,
                            })}{' '}
                            –{' '}
                            {formatDateTime(appointment.ends_at_local, 'en-US', {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                                timeZone: appointment.timezone,
                            })}
                        </p>
                        <p>
                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                Timezone:
                            </span>{' '}
                            {appointment.timezone}
                        </p>
                        <p>
                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                Property:
                            </span>{' '}
                            {appointment.address_line || '—'}
                        </p>
                        {appointment.notes !== null && (
                            <p>
                                <span className="font-semibold text-surface-900 dark:text-surface-0">
                                    Notes:
                                </span>{' '}
                                {appointment.notes}
                            </p>
                        )}
                        {appointment.cancel_reason !== null && (
                            <p>
                                <span className="font-semibold text-surface-900 dark:text-surface-0">
                                    Cancelled:
                                </span>{' '}
                                {appointment.cancel_reason}
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <p>
                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                When:
                            </span>{' '}
                            {formatDateTime(event.starts_at_local, 'en-US', {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                                timeZone: event.timezone,
                            })}
                        </p>
                        <p>
                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                Timezone:
                            </span>{' '}
                            {event.timezone}
                        </p>
                    </>
                )}

                {canViewAppointment && isError ? (
                    <p role="alert" className="text-sm text-aq-danger-strong">
                        The full appointment could not be loaded. Open the appointment file to
                        retry.
                    </p>
                ) : null}
            </div>

            <div className="mt-6 flex flex-col gap-2 px-4">
                {canViewAppointment && (
                    <Button
                        render={<Link href={`/appointments/${event.uuid}`} />}
                        nativeButton={false}
                        className="w-full"
                    >
                        Open appointment
                    </Button>
                )}
                {canViewLead && (
                    <Button
                        render={
                            <Link href={`/leads/${appointment?.lead_uuid ?? event.lead_uuid}`} />
                        }
                        nativeButton={false}
                        variant="outline"
                        className="w-full"
                    >
                        Open lead
                    </Button>
                )}
            </div>
        </>
    );
}
