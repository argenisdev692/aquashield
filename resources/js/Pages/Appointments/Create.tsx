/**
 * Appointments/Create — book a visit for an existing lead.
 *
 * The lead is found through the module picker; the availability window, the
 * travel buffer and the daily cap are enforced by the scheduling action, so
 * this screen only owns the form contract and the server's 422 bag.
 */
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AppLayout from '@/Layouts/AppLayout';
import { useZodForm } from '@/lib/forms';
import AppointmentFormFields, {
    type AppointmentOptions,
} from '@/Modules/LeadAppointment/components/AppointmentFormFields';
import {
    appointmentFormSchema,
    startsAtFrom,
    type AppointmentFormValues,
} from '@/Modules/LeadAppointment/schemas/appointment';

type CreateProps = {
    options: AppointmentOptions;
    defaults: {
        duration_minutes: number;
        timezone: string;
    };
};

export default function AppointmentsCreate({ options, defaults }: CreateProps) {
    const initial: AppointmentFormValues = {
        lead_uuid: '',
        kind: 'inspection',
        date: '',
        time: '',
        duration_minutes: defaults.duration_minutes,
        region: '',
        is_emergency: false,
        notes: '',
    };

    const { form, errors, submit, touch, focusFirstError } = useZodForm({
        initial,
        schema: appointmentFormSchema,
    });

    return (
        <AppLayout title="New appointment">
            <Head title="New appointment" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            Schedule a visit
                        </h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            The office's availability windows and the single-crew daily cap are
                            checked on the server.
                        </p>
                    </div>

                    <Button
                        render={<Link href="/appointments" />}
                        nativeButton={false}
                        variant="outline"
                        className="h-9 gap-2"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to appointments
                    </Button>
                </div>

                <FormErrorSummary errors={errors} onFieldFocus={() => focusFirstError()} />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(() => {
                            form.transform((data) => ({
                                lead_uuid: data.lead_uuid,
                                kind: data.kind,
                                starts_at: startsAtFrom(data.date, data.time),
                                duration_minutes: data.duration_minutes,
                                region: data.region === '' ? null : data.region,
                                notes: data.notes.trim() === '' ? null : data.notes.trim(),
                                is_emergency: data.is_emergency,
                            }));

                            form.post('/appointments', { preserveScroll: true });
                        });
                    }}
                    className="rounded-xl border border-surface-200 bg-surface-0 p-4 sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <AppointmentFormFields
                        values={form.data}
                        errors={errors}
                        options={options}
                        onChange={(values) => form.setData(values)}
                        onBlur={(key) => touch(key)}
                        disabled={form.processing}
                        timezone={defaults.timezone}
                    />

                    <FormActions
                        submitLabel="Schedule appointment"
                        loading={form.processing}
                        disabled={form.processing}
                        cancelLabel="Cancel"
                        onCancel={() => router.visit('/appointments')}
                        className="mt-8"
                    />
                </form>
            </div>
        </AppLayout>
    );
}
