/**
 * BulkClosureDialog — close a range of days in one write.
 *
 * The range endpoint already overwrites an existing row for each date and
 * marks it manual, so the copy says that instead of pretending the operation is
 * purely additive. Server errors (range cap, validation) land on the same
 * field kit through `HttpRequestError.fieldErrors`.
 */
import { useState } from 'react';
import DateField from '@/Components/Forms/DateField';
import TextField from '@/Components/Forms/TextField';
import { FormDialog } from '@/Components/Forms/FormOverlay';
import { NO_SERVER_ERROR, serverErrorFrom, useZodForm, type ServerErrorState } from '@/lib/forms';
import { useAvailabilityMutations } from '@/Modules/LeadAppointment/hooks/useAvailabilityMutations';
import {
    bulkClosureSchema,
    type BulkClosureFormValues,
} from '@/Modules/LeadAppointment/schemas/availability';

interface BulkClosureDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    maxDays: number;
    onCreated: () => void;
}

export default function BulkClosureDialog({
    open,
    onOpenChange,
    maxDays,
    onCreated,
}: BulkClosureDialogProps) {
    const mutations = useAvailabilityMutations();
    const [serverError, setServerError] = useState<ServerErrorState>(NO_SERVER_ERROR);

    const initial: BulkClosureFormValues = { from: '', to: '', reason: '' };

    const { form, errors, submit, touch } = useZodForm({ initial, schema: bulkClosureSchema });

    const mergedErrors = { ...errors, ...serverError.fieldErrors };

    const onSubmit = (): void => {
        setServerError(NO_SERVER_ERROR);

        submit(() => {
            void mutations.closeRange
                .mutateAsync(form.data)
                .then(() => {
                    form.reset();
                    onOpenChange(false);
                    onCreated();
                })
                .catch((caught: unknown) => {
                    setServerError(
                        serverErrorFrom(caught, 'The closure could not be created. Try again.'),
                    );
                });
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Close a range of days"
            description={`Every date in the range becomes unavailable. Existing rows for those dates are replaced with a manual closure, and the range is limited to ${maxDays} days.`}
            form={{
                processing: form.processing || mutations.closeRange.isPending,
                isDirty: form.isDirty,
            }}
            errors={mergedErrors}
            onSubmit={onSubmit}
            submitLabel="Close days"
        >
            {serverError.serverError !== null && (
                <p
                    role="alert"
                    className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    {serverError.serverError}
                </p>
            )}

            <DateField
                label="First closed day"
                name="from"
                value={form.data.from === '' ? null : form.data.from}
                onChange={(value) => form.setData('from', value ?? '')}
                onBlur={() => touch('from')}
                error={mergedErrors.from}
                required
                disabled={mutations.closeRange.isPending}
            />

            <DateField
                label="Last closed day"
                name="to"
                value={form.data.to === '' ? null : form.data.to}
                onChange={(value) => form.setData('to', value ?? '')}
                onBlur={() => touch('to')}
                error={mergedErrors.to}
                required
                disabled={mutations.closeRange.isPending}
            />

            <TextField
                label="Reason"
                name="reason"
                value={form.data.reason}
                onChange={(value) => form.setData('reason', value)}
                onBlur={() => touch('reason')}
                error={mergedErrors.reason}
                placeholder="Crew vacation, hurricane, office move…"
                maxLength={255}
                disabled={mutations.closeRange.isPending}
            />
        </FormDialog>
    );
}
