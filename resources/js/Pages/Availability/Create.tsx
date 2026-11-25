/**
 * Availability/Create — close a day or open an extra one.
 *
 * The calendar can prefill the date (`?date=YYYY-MM-DD`). The server enforces
 * the single active exception per date; this page owns the form contract and
 * the 422 bag only.
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

interface AvailabilityCreateProps {
    defaults: {
        date: string | null;
        is_available: boolean;
    };
    timezone: string;
}

export default function AvailabilityCreate({ defaults, timezone }: AvailabilityCreateProps) {
    const initial: AvailabilityExceptionFormValues = {
        date: defaults.date ?? '',
        is_available: defaults.is_available,
        start_time: '08:00',
        end_time: '17:00',
        reason: '',
    };

    const { form, errors, submit, touch, focusFirstError } = useZodForm({
        initial,
        schema: availabilityExceptionSchema,
    });
    const invalidateCalendar = useAvailabilityCalendarInvalidator();

    return (
        <AppLayout title="New availability exception">
            <Head title="New availability exception" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            Block or open a day
                        </h1>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-400">
                            A closure overrides the weekly hours for that date. The operator sees it
                            before booking a visit in {timezone}.
                        </p>
                    </div>

                    <Button
                        render={<Link href="/availability" />}
                        nativeButton={false}
                        variant="outline"
                        className="h-9 gap-2"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to availability
                    </Button>
                </div>

                <FormErrorSummary errors={errors} onFieldFocus={() => focusFirstError()} />

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

                            form.post('/availability', {
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
                    />

                    <FormActions
                        submitLabel="Save day"
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
