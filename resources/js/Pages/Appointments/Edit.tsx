/**
 * Appointments/Edit — move a visit.
 *
 * Rescheduling is not a status: the server records the previous slot, appends
 * the reason to the appointment notes and re-runs the availability guard. The
 * form therefore asks for a reason, not a free-form edit.
 */
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import DateField from '@/Components/Forms/DateField';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import NumberField from '@/Components/Forms/NumberField';
import TextareaField from '@/Components/Forms/TextareaField';
import TimeField from '@/Components/Forms/TimeField';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, todayIso } from '@/lib/date';
import { useUnsavedChangesWarning, useZodForm } from '@/lib/forms';
import {
    durationBetween,
    localParts,
    rescheduleSchema,
    startsAtFrom,
    type RescheduleFormValues,
} from '@/Modules/LeadAppointment/schemas/appointment';
import AvailabilityNotice from '@/Modules/LeadAppointment/components/AvailabilityNotice';
import { humanize, statusVariant } from './components/appointmentDisplay';

type Appointment = Modules.LeadAppointment.Application.Dtos.AppointmentData;

export default function AppointmentsEdit({ appointment }: { appointment: Appointment }) {
    const parts = localParts(appointment.starts_at_local);
    const currentDuration = durationBetween(appointment.starts_at, appointment.ends_at) ?? 60;

    const initial: RescheduleFormValues = {
        date: parts.date,
        time: parts.time,
        duration_minutes: currentDuration,
        reason: '',
    };

    const { form, errors, submit, touch } = useZodForm({
        initial,
        schema: rescheduleSchema,
    });

    useUnsavedChangesWarning(form.isDirty);

    return (
        <AppLayout title="Reschedule appointment">
            <Head title={`Reschedule ${appointment.customer_name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            Reschedule {appointment.customer_name}
                        </h2>
                        <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-surface-500 dark:text-surface-300">
                            <Tag variant={statusVariant(appointment.status)}>
                                {humanize(appointment.status)}
                            </Tag>
                            <span>{humanize(appointment.kind)}</span>
                        </p>
                    </div>

                    <Button
                        render={<Link href={`/appointments/${appointment.uuid}`} />}
                        nativeButton={false}
                        variant="outline"
                        className="h-9 gap-2"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to appointment
                    </Button>
                </div>

                <section className="rounded-xl border border-surface-200 bg-surface-50 p-4 dark:border-surface-800 dark:bg-surface-800/40">
                    <h2 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        Current slot
                    </h2>
                    <p className="mt-1 text-sm tabular-nums text-surface-600 dark:text-surface-300">
                        {formatDateTime(appointment.starts_at_local, 'en-US', {
                            dateStyle: 'full',
                            timeStyle: 'short',
                            timeZone: appointment.timezone,
                        })}
                    </p>
                    <p className="text-xs text-surface-500 dark:text-surface-300">
                        {appointment.timezone} · {humanize(appointment.region)} ·{' '}
                        {appointment.address_line}
                    </p>
                </section>

                <FormErrorSummary errors={errors} />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(() => {
                            form.transform((data) => ({
                                starts_at: startsAtFrom(data.date, data.time),
                                duration_minutes: data.duration_minutes,
                                reason: data.reason,
                            }));

                            form.post(`/appointments/${appointment.uuid}/reschedule`, {
                                preserveScroll: true,
                            });
                        });
                    }}
                    className="rounded-xl border border-surface-200 bg-surface-0 p-4 sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <DateField
                                label="New date"
                                name="date"
                                value={form.data.date === '' ? null : form.data.date}
                                onChange={(value) => form.setData('date', value ?? '')}
                                onBlur={() => touch('date')}
                                error={errors.date ?? errors.starts_at}
                                required
                                min={todayIso(appointment.timezone)}
                            />

                            <AvailabilityNotice
                                date={form.data.date}
                                region={appointment.region}
                                onPickDate={(date) => form.setData('date', date)}
                            />
                        </div>

                        <TimeField
                            label="New start time"
                            name="time"
                            value={form.data.time}
                            onChange={(value) => form.setData('time', value)}
                            onBlur={() => touch('time')}
                            error={errors.time ?? errors.starts_at}
                            hint={`Times are read in ${appointment.timezone}.`}
                            required
                        />

                        <NumberField
                            label="Duration"
                            name="duration_minutes"
                            value={form.data.duration_minutes}
                            onChange={(value) => form.setData('duration_minutes', value ?? 15)}
                            onBlur={() => touch('duration_minutes')}
                            error={errors.duration_minutes}
                            min={15}
                            max={480}
                            step={15}
                            suffix="minutes"
                            required
                        />
                    </div>

                    <TextareaField
                        label="Reason"
                        name="reason"
                        value={form.data.reason}
                        onChange={(value) => form.setData('reason', value)}
                        onBlur={() => touch('reason')}
                        error={errors.reason}
                        rows={3}
                        required
                        placeholder="Customer asked for a later window, adjuster availability…"
                        hint="The reason is appended to the appointment notes and sent to the customer."
                    />

                    <FormActions
                        submitLabel="Reschedule appointment"
                        loading={form.processing}
                        disabled={form.processing}
                        cancelLabel="Cancel"
                        onCancel={() => router.visit(`/appointments/${appointment.uuid}`)}
                        className="mt-8"
                    />
                </form>
            </div>
        </AppLayout>
    );
}
