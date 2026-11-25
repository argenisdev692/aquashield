/**
 * Availability/Edit — change one date's exception.
 *
 * The source travels with the row: a holiday synced from the federal calendar
 * keeps that source on update, so the form says so before the operator spends
 * a minute editing text the next sync may replace.
 */
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AppLayout from '@/Layouts/AppLayout';
import { useZodForm } from '@/lib/forms';
import {
    availabilityExceptionSchema,
    wireWindow,
    type AvailabilityExceptionFormValues,
} from '@/Modules/LeadAppointment/schemas/availability';
import { useAvailabilityCalendarInvalidator } from '@/Modules/LeadAppointment/hooks/useAvailabilityCalendar';
import ExceptionFormFields from './components/ExceptionFormFields';

type ExceptionRow = Modules.LeadAppointment.Application.Dtos.AvailabilityExceptionData;

interface AvailabilityEditProps {
    exception: ExceptionRow;
    timezone: string;
}

export default function AvailabilityEdit({ exception, timezone }: AvailabilityEditProps) {
    const initial: AvailabilityExceptionFormValues = {
        date: exception.date,
        is_available: exception.is_available,
        start_time: exception.start_time ?? '08:00',
        end_time: exception.end_time ?? '17:00',
        reason: exception.reason ?? '',
    };

    const { form, errors, submit, touch } = useZodForm({
        initial,
        schema: availabilityExceptionSchema,
    });
    const invalidateCalendar = useAvailabilityCalendarInvalidator();

    return (
        <AppLayout title="Edit availability exception">
            <Head title="Edit availability exception" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            Edit {exception.date}
                        </h2>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            Dates and windows are read in {timezone}. A closed day disappears from
                            the operator's bookable slots.
                        </p>
                    </div>

                    <Button
                        render={<Link href="/availability" />}
                        nativeButton={false}
                        variant="outline"
                        size="header"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to availability
                    </Button>
                </div>

                <FormErrorSummary errors={errors} />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(() => {
                            form.transform((data) => ({
                                date: data.date,
                                is_available: data.is_available,
                                ...wireWindow(data),
                                reason: data.reason.trim() === '' ? null : data.reason.trim(),
                            }));

                            form.put(`/availability/${exception.uuid}`, {
                                preserveScroll: true,
                                onSuccess: invalidateCalendar,
                            });
                        });
                    }}
                    className="rounded-xl border border-surface-200 bg-surface-0 p-4 sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <ExceptionFormFields
                        values={form.data}
                        errors={errors}
                        onChange={(values) => form.setData(values)}
                        onBlur={(key) => touch(key)}
                        disabled={form.processing}
                        source={exception.source}
                    />

                    <FormActions
                        submitLabel="Save changes"
                        loading={form.processing}
                        disabled={form.processing}
                        cancelLabel="Cancel"
                        onCancel={() => router.visit('/availability')}
                        className="mt-8"
                    />
                </form>
            </div>
        </AppLayout>
    );
}
