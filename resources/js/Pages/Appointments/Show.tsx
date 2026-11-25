/**
 * Appointments/Show — the visit file and its lifecycle actions.
 *
 * The server owns the transition map; this screen only offers the actions that
 * are legal from the current status. Cancel asks for a reason through the form
 * dialog, and delete is confirmed with the shared Base UI dialog.
 */
import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Check,
    CircleCheck,
    Mail,
    MapPin,
    Pencil,
    Phone,
    RotateCcw,
    Trash2,
    X,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import { FormDialog } from '@/Components/Forms/FormOverlay';
import TextareaField from '@/Components/Forms/TextareaField';
import CrudDialog from '@/Components/DataTable/CrudDialog';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/date';
import { useZodForm } from '@/lib/forms';
import { cancelAppointmentSchema } from '@/Modules/LeadAppointment/schemas/appointment';
import {
    canCancel,
    canComplete,
    canConfirm,
    canReschedule,
    humanize,
    statusVariant,
} from './components/appointmentDisplay';

type Appointment = Modules.LeadAppointment.Application.Dtos.AppointmentData;

type ShowProps = {
    appointment: Appointment;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canViewLead: boolean;
    errors: Record<string, string>;
};

export default function AppointmentsShow() {
    const { appointment, canUpdate, canDelete, canRestore, canViewLead, errors } =
        usePage<ShowProps>().props;
    const deleted = appointment.deleted_at !== null;

    const [cancelOpen, setCancelOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [acting, setActing] = useState(false);

    const cancel = useZodForm({
        initial: { reason: '' },
        schema: cancelAppointmentSchema,
    });

    const post = (url: string): void => {
        setActing(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setActing(false) });
    };

    const restore = (): void => {
        setActing(true);
        router.patch(
            `/appointments/${appointment.uuid}/restore`,
            {},
            { preserveScroll: true, onFinish: () => setActing(false) },
        );
    };

    return (
        <AppLayout title="Appointment">
            <Head title={`${appointment.customer_name} · Appointment`} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                                {appointment.customer_name}
                            </h2>
                            <Tag variant={statusVariant(appointment.status)}>
                                {humanize(appointment.status)}
                            </Tag>
                            {deleted && <Tag variant="destructive">Deleted</Tag>}
                        </div>
                        <p className="mt-1 text-sm text-surface-500 dark:text-surface-300">
                            {humanize(appointment.kind)} · {humanize(appointment.region)} ·{' '}
                            {appointment.uuid}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<Link href="/appointments" />}
                            nativeButton={false}
                            variant="outline"
                            className="h-9 gap-2"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Appointments
                        </Button>

                        {!deleted && canUpdate && canReschedule(appointment.status) && (
                            <Button
                                render={<Link href={`/appointments/${appointment.uuid}/edit`} />}
                                nativeButton={false}
                                variant="outline"
                                className="h-9 gap-2"
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Reschedule
                            </Button>
                        )}

                        {deleted && canRestore && (
                            <Button
                                variant="outline"
                                className="h-9 gap-2"
                                disabled={acting}
                                onClick={restore}
                            >
                                <RotateCcw className="size-4" aria-hidden="true" />
                                Restore
                            </Button>
                        )}

                        {!deleted && canDelete && (
                            <Button
                                variant="destructive"
                                className="h-9 gap-2"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                {(errors.status !== undefined || errors.starts_at !== undefined) && (
                    <p
                        role="alert"
                        className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                    >
                        {errors.status ?? errors.starts_at}
                    </p>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="flex flex-col gap-4 rounded-xl border border-surface-200 bg-surface-0 p-4 lg:col-span-2 dark:border-surface-800 dark:bg-surface-900">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-surface-900 dark:text-surface-0">
                            <CalendarClock className="size-4 text-aq-accent" aria-hidden="true" />
                            Visit
                        </h2>

                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                    Starts
                                </dt>
                                <dd className="mt-0.5 text-sm tabular-nums text-surface-900 dark:text-surface-0">
                                    {formatDateTime(appointment.starts_at_local, 'en-US', {
                                        dateStyle: 'full',
                                        timeStyle: 'short',
                                        timeZone: appointment.timezone,
                                    })}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                    Ends
                                </dt>
                                <dd className="mt-0.5 text-sm tabular-nums text-surface-900 dark:text-surface-0">
                                    {formatDateTime(appointment.ends_at_local, 'en-US', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                        timeZone: appointment.timezone,
                                    })}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                    Property
                                </dt>
                                <dd className="mt-0.5 flex items-start gap-2 text-sm text-surface-700 dark:text-surface-200">
                                    <MapPin
                                        className="mt-0.5 size-4 shrink-0 text-surface-400"
                                        aria-hidden="true"
                                    />
                                    {appointment.address_line !== ''
                                        ? appointment.address_line
                                        : 'No address on file'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                    Timezone
                                </dt>
                                <dd className="mt-0.5 text-sm text-surface-700 dark:text-surface-200">
                                    {appointment.timezone}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                    Google event
                                </dt>
                                <dd className="mt-0.5 text-sm text-surface-700 dark:text-surface-200">
                                    {appointment.google_event_id ?? 'Not synced'}
                                </dd>
                            </div>
                            {appointment.previous_starts_at !== null && (
                                <div className="sm:col-span-2">
                                    <dt className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                        Previous slot
                                    </dt>
                                    <dd className="mt-0.5 text-sm tabular-nums text-surface-700 dark:text-surface-200">
                                        {formatDateTime(appointment.previous_starts_at, 'en-US', {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                            timeZone: appointment.timezone,
                                        })}
                                    </dd>
                                </div>
                            )}
                        </dl>

                        <div>
                            <h3 className="text-xs font-medium text-surface-500 dark:text-surface-300">
                                Notes
                            </h3>
                            <p className="mt-1 whitespace-pre-line text-sm text-surface-700 dark:text-surface-200">
                                {appointment.notes ?? 'No notes for this visit.'}
                            </p>
                        </div>

                        {appointment.cancel_reason !== null && (
                            <p className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong">
                                Cancelled: {appointment.cancel_reason}
                            </p>
                        )}
                    </section>

                    <div className="flex flex-col gap-4">
                        <section className="flex flex-col gap-3 rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900">
                            <h2 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                                Contact
                            </h2>

                            {appointment.customer_phone !== null ? (
                                <a
                                    href={`tel:${appointment.customer_phone}`}
                                    className="flex items-center gap-2 text-sm text-surface-700 transition-colors duration-150 hover:text-aq-accent dark:text-surface-200"
                                >
                                    <Phone className="size-4 text-surface-400" aria-hidden="true" />
                                    {appointment.customer_phone}
                                </a>
                            ) : (
                                <span className="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-300">
                                    <Phone className="size-4 text-surface-400" aria-hidden="true" />
                                    No phone
                                </span>
                            )}

                            {appointment.customer_email !== null ? (
                                <a
                                    href={`mailto:${appointment.customer_email}`}
                                    className="flex items-center gap-2 break-all text-sm text-surface-700 transition-colors duration-150 hover:text-aq-accent dark:text-surface-200"
                                >
                                    <Mail className="size-4 text-surface-400" aria-hidden="true" />
                                    {appointment.customer_email}
                                </a>
                            ) : (
                                <span className="flex items-center gap-2 break-all text-sm text-surface-500 dark:text-surface-300">
                                    <Mail className="size-4 text-surface-400" aria-hidden="true" />
                                    No email
                                </span>
                            )}

                            {canViewLead && (
                                <Button
                                    render={<Link href={`/leads/${appointment.lead_uuid}`} />}
                                    nativeButton={false}
                                    variant="outline"
                                    className="mt-1 h-9"
                                >
                                    Open lead
                                </Button>
                            )}
                        </section>

                        {!deleted && canUpdate && (
                            <section className="flex flex-col gap-2 rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-800 dark:bg-surface-900">
                                <h2 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                                    Lifecycle
                                </h2>

                                {canConfirm(appointment.status) && (
                                    <Button
                                        variant="outline"
                                        className="h-9 justify-start gap-2"
                                        disabled={acting}
                                        onClick={() =>
                                            post(`/appointments/${appointment.uuid}/confirm`)
                                        }
                                    >
                                        <Check className="size-4" aria-hidden="true" />
                                        Confirm visit
                                    </Button>
                                )}

                                {canComplete(appointment.status) && (
                                    <Button
                                        variant="outline"
                                        className="h-9 justify-start gap-2"
                                        disabled={acting}
                                        onClick={() =>
                                            post(`/appointments/${appointment.uuid}/complete`)
                                        }
                                    >
                                        <CircleCheck className="size-4" aria-hidden="true" />
                                        Mark completed
                                    </Button>
                                )}

                                {canCancel(appointment.status) && (
                                    <Button
                                        variant="outline"
                                        className="h-9 justify-start gap-2"
                                        disabled={acting}
                                        onClick={() => setCancelOpen(true)}
                                    >
                                        <X className="size-4" aria-hidden="true" />
                                        Cancel visit
                                    </Button>
                                )}
                            </section>
                        )}
                    </div>
                </div>
            </div>

            <FormDialog
                open={cancelOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setCancelOpen(false);
                    }
                }}
                title="Cancel this visit?"
                description="The customer is notified and the slot is released immediately."
                form={{ processing: cancel.form.processing, isDirty: cancel.form.isDirty }}
                errors={cancel.errors}
                onSubmit={() => {
                    cancel.submit(() => {
                        cancel.form.post(`/appointments/${appointment.uuid}/cancel`, {
                            preserveScroll: true,
                            onSuccess: () => setCancelOpen(false),
                        });
                    });
                }}
                submitLabel="Cancel visit"
                destructive
                guardDirty={false}
            >
                <TextareaField
                    label="Reason"
                    name="reason"
                    value={cancel.form.data.reason}
                    onChange={(value) => cancel.form.setData('reason', value)}
                    onBlur={() => cancel.touch('reason')}
                    error={cancel.errors.reason}
                    rows={3}
                    required
                    placeholder="Weather, customer request, access issue…"
                />
            </FormDialog>

            <CrudDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title="Delete this appointment?"
                description={`${appointment.customer_name} moves to the trash. The record can be restored from the deleted view.`}
                confirmLabel="Delete appointment"
                confirmVariant="destructive"
                onConfirm={() => {
                    setDeleteOpen(false);
                    router.delete(`/appointments/${appointment.uuid}`);
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    The calendar slot is freed and the lead's status is not changed.
                </p>
            </CrudDialog>
        </AppLayout>
    );
}
