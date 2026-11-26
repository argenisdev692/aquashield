/**
 * ScheduleAppointmentDialog — the dashboard's write path from the calendar.
 *
 * The page stays mounted: the dialog validates with Zod, posts JSON through
 * the module hook (the only network boundary) and asks the page to reload its
 * Inertia props. A 422 is mapped back onto the same field kit the page uses.
 */
import { useState } from 'react';
import { FormDialog } from '@/Components/Forms/FormOverlay';
import { HttpRequestError } from '@/lib/http';
import { useZodForm } from '@/lib/forms';
import { isServiceRegion } from '@/lib/regions';
import AppointmentFormFields from './AppointmentFormFields';
import {
    appointmentFormSchema,
    startsAtFrom,
    type AppointmentFormValues,
} from '../schemas/appointment';
import { useScheduleAppointment } from '../hooks/useScheduleAppointment';
import type { AppointmentOptions } from './AppointmentFormFields';

interface ScheduleAppointmentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: AppointmentOptions;
    defaultDuration: number;
    timezone?: string;
    defaultDate?: string;
    lead?: { uuid: string; name: string; phone: string | null; region: string | null } | null;
    onScheduled?: () => void;
}

export default function ScheduleAppointmentDialog({
    open,
    onOpenChange,
    options,
    defaultDuration,
    timezone,
    defaultDate = '',
    lead = null,
    onScheduled,
}: ScheduleAppointmentDialogProps) {
    const schedule = useScheduleAppointment();
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [serverError, setServerError] = useState<string | null>(null);

    const initial: AppointmentFormValues = {
        lead_uuid: lead?.uuid ?? '',
        kind: 'inspection',
        date: defaultDate,
        time: '',
        duration_minutes: defaultDuration,
        region: isServiceRegion(lead?.region) ? lead.region : '',
        is_emergency: false,
        notes: '',
    };

    const { form, errors, submit, submitAttempts, touch } = useZodForm({
        initial,
        schema: appointmentFormSchema,
    });

    const mergedErrors = { ...errors, ...fieldErrors };

    const onSubmit = (): void => {
        setServerError(null);
        setFieldErrors({});

        submit(() => {
            void schedule
                .schedule({
                    lead_uuid: lead?.uuid ?? form.data.lead_uuid,
                    kind: form.data.kind,
                    starts_at: startsAtFrom(form.data.date, form.data.time),
                    duration_minutes: form.data.duration_minutes,
                    region: form.data.region === '' ? null : form.data.region,
                    notes: form.data.notes.trim() === '' ? null : form.data.notes.trim(),
                    is_emergency: form.data.is_emergency,
                })
                .then(() => {
                    form.reset();
                    onOpenChange(false);
                    onScheduled?.();
                })
                .catch((caught: unknown) => {
                    if (caught instanceof HttpRequestError) {
                        const fields = { ...caught.fieldErrors };
                        setFieldErrors(fields);
                        setServerError(Object.keys(fields).length === 0 ? caught.message : null);

                        return;
                    }

                    setServerError(
                        caught instanceof Error && caught.message !== ''
                            ? caught.message
                            : 'The appointment could not be scheduled. Try again.',
                    );
                });
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={lead === null ? 'Schedule a visit' : `Schedule a visit for ${lead.name}`}
            description="The office availability window, the travel buffer and the single-crew daily cap are checked on the server."
            form={{
                processing: form.processing || schedule.isPending,
                isDirty: form.isDirty,
                submitAttempts,
            }}
            errors={mergedErrors}
            onSubmit={onSubmit}
            submitLabel="Schedule appointment"
        >
            {serverError !== null && (
                <p
                    role="alert"
                    className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    {serverError}
                </p>
            )}

            <AppointmentFormFields
                values={form.data}
                errors={mergedErrors}
                options={options}
                onChange={(values) => {
                    form.setData(values);
                    // A 422 bag from the previous payload is stale; clear it as
                    // soon as the user corrects anything.
                    setFieldErrors({});
                }}
                onBlur={(key) => touch(key)}
                disabled={schedule.isPending}
                timezone={timezone}
                fixedLead={
                    lead === null
                        ? null
                        : { name: lead.name, phone: lead.phone, region: lead.region }
                }
            />
        </FormDialog>
    );
}
