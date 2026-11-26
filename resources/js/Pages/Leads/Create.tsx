/**
 * Leads/Create — internal lead entry for staff.
 *
 * Meta Ads and the public website use the capture API; this screen exists for
 * the phone call, the referral and the walk-in. The server stamps the source
 * as `manual`, so the form cannot claim a campaign.
 */
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AppLayout from '@/Layouts/AppLayout';
import { useZodForm } from '@/lib/forms';
import { emptyLeadFormValues, leadFormSchema } from '@/Modules/LeadAppointment/schemas/lead';
import LeadForm from '@/Pages/Leads/components/LeadForm';

type LeadOptions = {
    statuses: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    regions: { value: string; label: string }[];
    owners: Modules.LeadAppointment.Application.Dtos.AssignableOwnerData[];
};

export default function LeadsCreate({
    options,
    googleMapsApiKey,
}: {
    options: LeadOptions;
    googleMapsApiKey: string;
}) {
    const { form, errors, submit, submitAttempts, touch } = useZodForm({
        initial: { ...emptyLeadFormValues },
        schema: leadFormSchema,
    });

    return (
        <AppLayout title="New lead">
            <Head title="New lead" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            New lead
                        </h2>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-300">
                            Log a lead the office took by phone, referral or walk-in.
                        </p>
                    </div>

                    <Button
                        render={<Link href="/leads" />}
                        nativeButton={false}
                        variant="outline"
                        className="h-9 gap-2"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Back to leads
                    </Button>
                </div>

                <FormErrorSummary errors={errors} submitAttempts={submitAttempts} />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(() => {
                            form.post('/leads', { preserveScroll: true });
                        });
                    }}
                    className="rounded-xl border border-surface-200 bg-surface-0 p-4 sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <LeadForm
                        values={form.data}
                        errors={errors}
                        options={options}
                        mode="create"
                        googleMapsApiKey={googleMapsApiKey}
                        onChange={(next) => form.setData(next)}
                        onBlur={(key) => touch(key)}
                        showCompany={false}
                    />

                    <FormActions
                        submitLabel="Create lead"
                        loading={form.processing}
                        disabled={form.processing}
                        cancelLabel="Cancel"
                        onCancel={() => router.visit('/leads')}
                        className="mt-8"
                    />
                </form>
            </div>
        </AppLayout>
    );
}
