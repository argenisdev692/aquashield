/**
 * LeadCreateDialog — the pipeline's quick-add modal.
 *
 * It reuses the exact create-page form (address autocomplete, phone library,
 * insurance flag) so there is one field contract, and posts JSON through the
 * module hook so the dashboard stays on the pipeline. A 422 is mapped back to
 * the same field kit the page uses.
 */
import { useState } from 'react';
import { FormDialog } from '@/Components/Forms/FormOverlay';
import { HttpRequestError } from '@/lib/http';
import { useZodForm } from '@/lib/forms';
import { emptyLeadFormValues, leadFormSchema } from '@/Modules/LeadAppointment/schemas/lead';
import { useCreateLead } from '@/Modules/LeadAppointment/hooks/useCreateLead';
import LeadForm from '@/Pages/Leads/components/LeadForm';
import type { LeadFormOptions } from '@/Pages/Leads/components/LeadForm';

interface LeadCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: LeadFormOptions;
    googleMapsApiKey: string;
    onCreated?: () => void;
}

export default function LeadCreateDialog({
    open,
    onOpenChange,
    options,
    googleMapsApiKey,
    onCreated,
}: LeadCreateDialogProps) {
    const create = useCreateLead();
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [serverError, setServerError] = useState<string | null>(null);

    const { form, errors, submit, submitAttempts, touch } = useZodForm({
        initial: { ...emptyLeadFormValues },
        schema: leadFormSchema,
    });

    const mergedErrors = { ...errors, ...fieldErrors };

    const onSubmit = (): void => {
        setServerError(null);
        setFieldErrors({});

        submit(() => {
            void create
                .create(form.data)
                .then(() => {
                    form.reset();
                    onOpenChange(false);
                    onCreated?.();
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
                            : 'The lead could not be created. Try again.',
                    );
                });
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="New lead"
            description="Log a lead the office took by phone, referral or walk-in. Meta Ads leads arrive through the ingest endpoint."
            form={{
                processing: form.processing || create.isPending,
                isDirty: form.isDirty,
                submitAttempts,
            }}
            errors={mergedErrors}
            onSubmit={onSubmit}
            submitLabel="Create lead"
            contentClassName="sm:max-w-2xl"
        >
            {serverError !== null && (
                <p
                    role="alert"
                    className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    {serverError}
                </p>
            )}

            <LeadForm
                values={form.data}
                errors={mergedErrors}
                options={options}
                mode="create"
                googleMapsApiKey={googleMapsApiKey}
                onChange={(values) => {
                    form.setData(values);
                    // The server bag described the previous payload; a
                    // corrected field must not keep its old 422.
                    setFieldErrors({});
                }}
                onBlur={(key) => touch(key)}
                showPreferredLanguage={false}
                showCompany={false}
            />
        </FormDialog>
    );
}
