/**
 * Leads/Show — the lead file: identity, property, insurance, inspection,
 * activity timeline and the audited stage control.
 */
import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil, Phone, RotateCcw, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Tag } from '@/Components/ui/tag';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import SelectField from '@/Components/Forms/SelectField';
import TextField from '@/Components/Forms/TextField';
import TextareaField from '@/Components/Forms/TextareaField';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatIsoDate } from '@/lib/date';
import { useZodForm } from '@/lib/forms';
import { humanize } from '@/lib/utils';
import { logActivitySchema, type LogActivityValues } from '@/Modules/LeadAppointment/schemas/lead';

type Lead = Modules.LeadAppointment.Application.Dtos.LeadData;
type Activity = Modules.LeadAppointment.Application.Dtos.LeadActivityData;

type ShowProps = {
    lead: Lead;
    activities: Activity[];
    transitions: Record<string, string[]>;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
};

const activityOptions = [
    { value: 'call', label: 'Call' },
    { value: 'email', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'note', label: 'Note' },
    { value: 'meeting', label: 'Meeting' },
];

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase dark:text-surface-300">
                {label}
            </dt>
            <dd className="text-sm break-words text-surface-900 dark:text-surface-100">
                {value === '' || value === null ? '—' : value}
            </dd>
        </div>
    );
}

export default function LeadsShow({
    lead,
    activities,
    transitions,
    canUpdate,
    canDelete,
    canRestore,
}: ShowProps) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [stageError, setStageError] = useState<string | null>(null);
    const [stage, setStage] = useState<string | null>(null);
    const pageErrors = usePage<{ errors: Record<string, string> }>().props.errors;
    const visibleStageError = stageError ?? pageErrors.status ?? null;

    const activityForm = useZodForm<LogActivityValues>({
        initial: { type: 'call', outcome: '', notes: '' },
        schema: logActivitySchema,
    });

    const targets = transitions[lead.status] ?? [];
    const deleted = lead.deleted_at !== null;

    const moveStage = (): void => {
        if (stage === null || stage === '') {
            return;
        }

        setStageError(null);

        router.post(
            `/leads/${lead.uuid}/status`,
            { status: stage, note: null },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setStageError(errors.status ?? 'That stage change is not allowed.');
                },
                onSuccess: () => setStage(null),
            },
        );
    };

    const phoneHref = lead.phone === null ? null : `tel:${lead.phone.replace(/[^\d+]/g, '')}`;

    const restore = (): void => {
        setActionError(null);

        router.patch(
            `/leads/${lead.uuid}/restore`,
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    setActionError(errors.lead ?? 'The lead could not be restored. Try again.');
                },
            },
        );
    };

    const destroy = (): void => {
        setDeleting(true);
        setActionError(null);

        router.delete(`/leads/${lead.uuid}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteOpen(false),
            onError: (errors) => {
                setActionError(errors.lead ?? 'The lead could not be deleted. Try again.');
            },
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <AppLayout title={lead.full_name}>
            <Head title={lead.full_name} />

            <div className="flex flex-col gap-6">
                {actionError !== null && (
                    <p
                        role="alert"
                        className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm font-medium text-aq-danger-strong"
                    >
                        {actionError}
                    </p>
                )}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <Link
                                href="/leads"
                                className="inline-flex items-center gap-1 text-sm text-surface-500 transition-colors duration-150 hover:text-aq-accent dark:text-surface-300"
                            >
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Leads
                            </Link>
                            <span className="text-surface-500 dark:text-surface-300">/</span>
                            <h2 className="truncate text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                                {lead.full_name}
                            </h2>
                            {lead.is_emergency && <Tag variant="destructive">Emergency</Tag>}
                            {lead.is_spam && (
                                <Tag variant="warning">Spam score {lead.spam_score}</Tag>
                            )}
                            {deleted && <Tag variant="destructive">Deleted</Tag>}
                        </div>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-300">
                            {humanize(lead.status)} · {humanize(lead.source)}
                            {lead.source_detail === null ? '' : ` (${lead.source_detail})`}
                            {lead.owner_name === null ? '' : ` · Owner ${lead.owner_name}`}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {deleted && canRestore && (
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 gap-2"
                                onClick={restore}
                            >
                                <RotateCcw className="size-4" aria-hidden="true" />
                                Restore
                            </Button>
                        )}
                        {canUpdate && !deleted && (
                            <Button
                                render={<Link href={`/leads/${lead.uuid}/edit`} />}
                                nativeButton={false}
                                className="h-9 gap-2"
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Edit lead
                            </Button>
                        )}
                        {canDelete && !deleted && (
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 gap-2 text-aq-danger-strong"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                {canUpdate && !deleted && (
                    <section
                        aria-label="Pipeline stage"
                        className="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-56 flex-1">
                                <SelectField
                                    label="Move stage"
                                    name="status"
                                    value={stage}
                                    onChange={(value) => setStage(value)}
                                    options={targets.map((target) => ({
                                        value: target,
                                        label: humanize(target),
                                    }))}
                                    placeholder={
                                        targets.length === 0
                                            ? 'No further stage changes'
                                            : 'Select the next stage'
                                    }
                                    disabled={targets.length === 0}
                                />
                            </div>
                            <Button
                                type="button"
                                className="h-9"
                                disabled={stage === null || stage === ''}
                                onClick={moveStage}
                            >
                                Update stage
                            </Button>
                        </div>
                        {visibleStageError !== null && (
                            <p role="alert" className="mt-2 text-sm text-aq-danger-strong">
                                {visibleStageError}
                            </p>
                        )}
                    </section>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900">
                        <h2 className="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Contact
                        </h2>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Field
                                label="Email"
                                value={
                                    lead.email === null ? (
                                        '—'
                                    ) : (
                                        <a
                                            href={`mailto:${lead.email}`}
                                            className="text-aq-accent transition-colors duration-150 hover:underline"
                                        >
                                            {lead.email}
                                        </a>
                                    )
                                }
                            />
                            <Field
                                label="Phone"
                                value={
                                    phoneHref === null ? (
                                        '—'
                                    ) : (
                                        <a
                                            href={phoneHref}
                                            className="inline-flex items-center gap-1 tabular-nums text-aq-accent transition-colors duration-150 hover:underline"
                                        >
                                            <Phone className="size-3" aria-hidden="true" />
                                            {lead.phone}
                                        </a>
                                    )
                                }
                            />
                            <Field label="Company" value={lead.company_name} />
                            <Field label="Language" value={lead.preferred_language.toUpperCase()} />
                            <Field label="Property" value={lead.address_line} />
                            <Field label="Region" value={humanize(lead.region ?? '')} />
                        </dl>
                    </section>

                    <section className="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900">
                        <h2 className="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Inspection and insurance
                        </h2>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Field
                                label="Inspection"
                                value={
                                    lead.inspection_date === null
                                        ? '—'
                                        : `${formatIsoDate(lead.inspection_date)} ${
                                              lead.inspection_time ?? ''
                                          }`
                                }
                            />
                            <Field
                                label="Inspection status"
                                value={
                                    lead.inspection_status === null
                                        ? '—'
                                        : humanize(lead.inspection_status)
                                }
                            />
                            <Field
                                label="Insurance property"
                                value={lead.insurance_property ? 'Yes' : 'No'}
                            />
                            <Field
                                label="Intent to claim"
                                value={
                                    lead.intent_to_claim === null
                                        ? 'Unknown'
                                        : lead.intent_to_claim
                                          ? 'Yes'
                                          : 'No'
                                }
                            />
                            <Field label="Carrier" value={lead.insurance_carrier} />
                            <Field label="Claim number" value={lead.claim_number} />
                        </dl>
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="rounded-xl border border-surface-200 bg-surface-0 p-4 lg:col-span-2 dark:border-surface-800 dark:bg-surface-900">
                        <h2 className="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Damage and notes
                        </h2>
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <Field label="Damage description" value={lead.damage_description} />
                            <Field label="Additional note" value={lead.additional_note} />
                            <Field label="Internal notes" value={lead.notes} />
                            <Field
                                label="Follow-up"
                                value={
                                    lead.follow_up_date === null
                                        ? '—'
                                        : formatIsoDate(lead.follow_up_date)
                                }
                            />
                        </dl>

                        {lead.attribution !== null && (
                            <div className="mt-4">
                                <h3 className="text-xs font-medium tracking-wide text-surface-500 uppercase dark:text-surface-300">
                                    Attribution
                                </h3>
                                <dl className="mt-2 grid gap-2 sm:grid-cols-2">
                                    {Object.entries(lead.attribution).map(([key, value]) => (
                                        <Field
                                            key={key}
                                            label={humanize(key)}
                                            value={
                                                typeof value === 'string' ||
                                                typeof value === 'number'
                                                    ? String(value)
                                                    : '—'
                                            }
                                        />
                                    ))}
                                </dl>
                            </div>
                        )}
                    </section>

                    <section className="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900">
                        <h2 className="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Timeline
                        </h2>

                        {activities.length === 0 ? (
                            <p className="py-4 text-sm text-surface-500 dark:text-surface-300">
                                No activity logged yet.
                            </p>
                        ) : (
                            <ol className="flex flex-col gap-3">
                                {activities.map((activity) => (
                                    <li key={activity.uuid} className="flex gap-2">
                                        <span
                                            aria-hidden="true"
                                            className="mt-1.5 size-2 shrink-0 rounded-full bg-aq-accent"
                                        />
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-surface-900 dark:text-surface-0">
                                                {humanize(activity.type)}
                                                {activity.outcome === null
                                                    ? ''
                                                    : ` · ${activity.outcome}`}
                                            </p>
                                            {activity.notes !== null && (
                                                <p className="text-sm whitespace-pre-line text-surface-600 dark:text-surface-300">
                                                    {activity.notes}
                                                </p>
                                            )}
                                            <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-300">
                                                {formatDateTime(activity.occurred_at)}
                                                {activity.actor_name === null
                                                    ? ''
                                                    : ` · ${activity.actor_name}`}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>

                {canUpdate && !deleted && (
                    <section className="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900">
                        <h2 className="mb-3 text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Log activity
                        </h2>

                        <FormErrorSummary errors={activityForm.errors} />

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                activityForm.submit(() => {
                                    activityForm.form.post(`/leads/${lead.uuid}/activities`, {
                                        preserveScroll: true,
                                        onSuccess: () => activityForm.form.reset(),
                                    });
                                });
                            }}
                            className="grid gap-3 sm:grid-cols-3"
                        >
                            <SelectField
                                label="Type"
                                name="type"
                                value={activityForm.form.data.type}
                                onChange={(value) =>
                                    activityForm.form.setData(
                                        'type',
                                        value === 'email' ||
                                            value === 'sms' ||
                                            value === 'note' ||
                                            value === 'meeting'
                                            ? value
                                            : 'call',
                                    )
                                }
                                onBlur={() => activityForm.touch('type')}
                                error={activityForm.errors.type}
                                options={activityOptions}
                            />

                            <TextField
                                label="Outcome"
                                name="outcome"
                                value={activityForm.form.data.outcome}
                                onChange={(value) => activityForm.form.setData('outcome', value)}
                                onBlur={() => activityForm.touch('outcome')}
                                error={activityForm.errors.outcome}
                                placeholder="Left voicemail"
                                maxLength={255}
                            />

                            <TextareaField
                                label="Notes"
                                name="notes"
                                value={activityForm.form.data.notes}
                                onChange={(value) => activityForm.form.setData('notes', value)}
                                onBlur={() => activityForm.touch('notes')}
                                error={activityForm.errors.notes}
                                rows={2}
                            />

                            <div className="flex items-end sm:col-span-3">
                                <Button
                                    type="submit"
                                    className="h-9"
                                    disabled={activityForm.form.processing}
                                >
                                    Add to timeline
                                </Button>
                            </div>
                        </form>
                    </section>
                )}

                <p className="text-xs text-surface-500 dark:text-surface-300">
                    Created {formatDateTime(lead.created_at)}
                    {lead.last_contacted_at === null
                        ? ''
                        : ` · Last contact ${formatDateTime(lead.last_contacted_at)}`}
                </p>
            </div>

            <Dialog
                open={deleteOpen}
                onOpenChange={(open) => {
                    setDeleteOpen(open);

                    if (open) {
                        setActionError(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this lead?</DialogTitle>
                        <DialogDescription>
                            {lead.full_name} moves to the trash. The record can be restored from the
                            list filters; the name and contact details stay in the audit trail.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                            disabled={deleting}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={destroy} disabled={deleting}>
                            {deleting ? 'Deleting…' : 'Delete lead'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
