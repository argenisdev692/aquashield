/**
 * Leads/Edit — update the lead's identity, property, qualification and
 * inspection fields. Stage changes go through their own endpoint because the
 * transition map is a server invariant, not a free-form edit.
 */
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import UnsavedChangesDialog from '@/Components/Forms/UnsavedChangesDialog';
import AppLayout from '@/Layouts/AppLayout';
import { useUnsavedChangesWarning, useZodForm } from '@/lib/forms';
import { useNavigationGuard } from '@/lib/leave-guard';
import { regionForAddress } from '@/lib/regions';
import { useLeadAppointmentInvalidator } from '@/Modules/LeadAppointment/hooks/useLeadAppointmentInvalidator';
import { leadFormSchema, type LeadFormValues } from '@/Modules/LeadAppointment/schemas/lead';
import LeadForm from '@/Modules/LeadAppointment/components/LeadForm';

type LeadOptions = {
    statuses: { value: string; label: string }[];
    sources: { value: string; label: string }[];
    regions: { value: string; label: string }[];
    owners: Modules.LeadAppointment.Application.Dtos.AssignableOwnerData[];
};

type Lead = Modules.LeadAppointment.Application.Dtos.LeadData;

const inspectionStatuses = ['pending', 'confirmed', 'completed', 'declined'] as const;

type InspectionStatus = (typeof inspectionStatuses)[number];

const inspectionStatusOf = (value: string | null): InspectionStatus | '' =>
    inspectionStatuses.find((status) => status === value) ?? '';

const valuesFrom = (lead: Lead): LeadFormValues => {
    const derivedRegion = regionForAddress(lead.state ?? '', lead.city ?? '', lead.zip_code ?? '');

    const storedRegion =
        lead.region === 'houston' || lead.region === 'dallas' || lead.region === 'florida'
            ? lead.region
            : '';

    return {
        first_name: lead.first_name,
        last_name: lead.last_name,
        company_name: lead.company_name ?? '',
        email: lead.email ?? '',
        phone: lead.phone ?? '',
        address: lead.address ?? '',
        address_2: lead.address2 ?? '',
        city: lead.city ?? '',
        state: lead.state === 'TX' || lead.state === 'FL' ? lead.state : '',
        zip_code: lead.zip_code ?? '',
        region: derivedRegion !== '' ? derivedRegion : storedRegion,
        damage_description: lead.damage_description ?? '',
        insurance_property: lead.insurance_property,
        intent_to_claim: lead.intent_to_claim ?? false,
        is_emergency: lead.is_emergency,
        follow_up_date: lead.follow_up_date ?? '',
        inspection_date: lead.inspection_date ?? '',
        inspection_time: lead.inspection_time ?? '',
        inspection_status: inspectionStatusOf(lead.inspection_status),
        additional_note: lead.additional_note ?? '',
        insurance_carrier: lead.insurance_carrier ?? '',
        claim_number: lead.claim_number ?? '',
        notes: lead.notes ?? '',
        owner_uuid: lead.owner_uuid ?? '',
        preferred_language: lead.preferred_language === 'es' ? 'es' : 'en',
        latitude: lead.latitude,
        longitude: lead.longitude,
    };
};

export default function LeadsEdit({
    lead,
    options,
    googleMapsApiKey,
}: {
    lead: Lead;
    options: LeadOptions;
    googleMapsApiKey: string;
}) {
    const { form, errors, submit, submitAttempts, touch } = useZodForm({
        initial: valuesFrom(lead),
        schema: leadFormSchema,
    });
    const invalidateLeadAppointment = useLeadAppointmentInvalidator();
    const navigationGuard = useNavigationGuard(form.isDirty);

    useUnsavedChangesWarning(form.isDirty);

    return (
        <AppLayout title="Edit lead">
            <Head title={`Edit ${lead.full_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            Edit {lead.full_name}
                        </h2>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-300">
                            Source: {lead.source_detail ?? lead.source}. Stage changes are audited
                            separately.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<Link href={`/leads/${lead.uuid}`} />}
                            nativeButton={false}
                            variant="outline"
                            className="h-9 gap-2"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back to lead
                        </Button>
                    </div>
                </div>

                <FormErrorSummary errors={errors} submitAttempts={submitAttempts} />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(() => {
                            form.put(`/leads/${lead.uuid}`, {
                                preserveScroll: true,
                                onSuccess: invalidateLeadAppointment,
                            });
                        });
                    }}
                    className="rounded-xl border border-surface-200 bg-surface-0 p-4 sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <LeadForm
                        values={form.data}
                        errors={errors}
                        options={options}
                        mode="edit"
                        googleMapsApiKey={googleMapsApiKey}
                        onChange={(next) => form.setData(next)}
                        onBlur={(key, values) => touch(key, values)}
                    />

                    <FormActions
                        submitLabel="Save changes"
                        loading={form.processing}
                        disabled={form.processing}
                        cancelLabel="Cancel"
                        onCancel={() => router.visit(`/leads/${lead.uuid}`)}
                        className="mt-8"
                    />
                </form>
            </div>

            <UnsavedChangesDialog guard={navigationGuard} />
        </AppLayout>
    );
}
