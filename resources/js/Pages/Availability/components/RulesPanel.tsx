/**
 * RulesPanel — the weekly opening hours behind every open day.
 *
 * The list is Inertia state (it arrived with the page visit); the dialogs are
 * the only network callers, through the module mutation hook. A saved or
 * deleted window asks the page to reload `rules` and invalidates the calendar
 * cache, so an open month cannot keep showing the old schedule.
 */
import { useState } from 'react';
import { Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import SelectField from '@/Components/Forms/SelectField';
import TimeField from '@/Components/Forms/TimeField';
import { FormDialog } from '@/Components/Forms/FormOverlay';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import { CrudDialog } from '@/Components/DataTable';
import { NO_SERVER_ERROR, serverErrorFrom, useZodForm, type ServerErrorState } from '@/lib/forms';
import { useAvailabilityMutations } from '@/Modules/LeadAppointment/hooks/useAvailabilityMutations';
import {
    availabilityRuleSchema,
    DAY_OPTIONS,
    type AvailabilityRuleFormValues,
} from '@/Modules/LeadAppointment/schemas/availability';

type Rule = Modules.LeadAppointment.Application.Dtos.AvailabilityRuleData;

/** The weekday a stored rule points at, or Monday for a row we cannot map. */
const dayOptionFor = (dayOfWeek: number): AvailabilityRuleFormValues['day_of_week'] =>
    DAY_OPTIONS.find((option) => Number(option.value) === dayOfWeek)?.value ?? '1';

interface RuleDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rule: Rule | null;
    onSaved: () => void;
}

function RuleDialog({ open, onOpenChange, rule, onSaved }: RuleDialogProps) {
    const { saveRule } = useAvailabilityMutations();
    const [serverError, setServerError] = useState<ServerErrorState>(NO_SERVER_ERROR);

    const initial: AvailabilityRuleFormValues = {
        day_of_week: rule === null ? '1' : dayOptionFor(rule.day_of_week),
        start_time: rule?.start_time ?? '08:00',
        end_time: rule?.end_time ?? '17:00',
    };

    const { form, errors, submit, touch } = useZodForm({ initial, schema: availabilityRuleSchema });
    const mergedErrors = { ...errors, ...serverError.fieldErrors };

    const onSubmit = (): void => {
        setServerError(NO_SERVER_ERROR);

        submit(() => {
            void saveRule
                .mutateAsync({ uuid: rule?.uuid ?? null, values: form.data })
                .then(() => {
                    form.reset();
                    onOpenChange(false);
                    onSaved();
                })
                .catch((caught: unknown) => {
                    setServerError(
                        serverErrorFrom(caught, 'The weekly window could not be saved. Try again.'),
                    );
                });
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={rule === null ? 'Add a weekly window' : `${rule.day_label} window`}
            description="Weekly windows apply to every date with no exception. A date closure always wins."
            form={{ processing: form.processing || saveRule.isPending, isDirty: form.isDirty }}
            errors={mergedErrors}
            onSubmit={onSubmit}
            submitLabel={rule === null ? 'Add window' : 'Save window'}
        >
            {serverError.serverError !== null && (
                <p
                    role="alert"
                    className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    {serverError.serverError}
                </p>
            )}

            <SelectField
                label="Weekday"
                name="day_of_week"
                value={form.data.day_of_week}
                onChange={(value) => {
                    const next = DAY_OPTIONS.find((option) => option.value === value);

                    if (next !== undefined) {
                        form.setData('day_of_week', next.value);
                    }
                }}
                onBlur={() => touch('day_of_week')}
                error={mergedErrors.day_of_week}
                options={DAY_OPTIONS.map((option) => ({
                    value: option.value,
                    label: option.label,
                }))}
                required
                disabled={saveRule.isPending}
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <TimeField
                    label="From"
                    name="start_time"
                    value={form.data.start_time}
                    onChange={(value) => form.setData('start_time', value)}
                    onBlur={() => touch('start_time')}
                    error={mergedErrors.start_time}
                    required
                    disabled={saveRule.isPending}
                />

                <TimeField
                    label="To"
                    name="end_time"
                    value={form.data.end_time}
                    onChange={(value) => form.setData('end_time', value)}
                    onBlur={() => touch('end_time')}
                    error={mergedErrors.end_time}
                    required
                    disabled={saveRule.isPending}
                />
            </div>
        </FormDialog>
    );
}

interface RulesPanelProps {
    rules: Rule[];
    /** Soft-deleted windows, read back so a delete is not one-way in the UI. */
    trashedRules: Rule[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    onChanged: () => void;
}

export default function RulesPanel({
    rules,
    trashedRules,
    canCreate,
    canUpdate,
    canDelete,
    canRestore,
    onChanged,
}: RulesPanelProps) {
    const { deleteRule, restoreRule } = useAvailabilityMutations();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Rule | null>(null);
    const [pendingDelete, setPendingDelete] = useState<Rule | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [restoreError, setRestoreError] = useState<string | null>(null);

    const openCreate = (): void => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (rule: Rule): void => {
        setEditing(rule);
        setDialogOpen(true);
    };

    const confirmDelete = (): void => {
        if (pendingDelete === null) {
            return;
        }

        void deleteRule.mutateAsync(pendingDelete.uuid).then(
            () => {
                setPendingDelete(null);
                setDeleteError(null);
                onChanged();
            },
            (caught: unknown) => {
                setDeleteError(
                    caught instanceof Error && caught.message !== ''
                        ? caught.message
                        : 'The window could not be deleted. Try again.',
                );
            },
        );
    };

    const restore = (rule: Rule): void => {
        setRestoreError(null);

        void restoreRule.mutateAsync(rule.uuid).then(
            () => onChanged(),
            (caught: unknown) => {
                setRestoreError(
                    caught instanceof Error && caught.message !== ''
                        ? caught.message
                        : 'The window could not be restored. Try again.',
                );
            },
        );
    };

    return (
        <section
            aria-labelledby="weekly-hours-heading"
            className="rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-800 dark:bg-surface-900"
        >
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-surface-200 px-4 py-3 dark:border-surface-800">
                <div>
                    <h2
                        id="weekly-hours-heading"
                        className="text-sm font-semibold text-surface-900 dark:text-surface-0"
                    >
                        Weekly opening hours
                    </h2>
                    <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                        The fallback for every day with no exception.
                    </p>
                </div>

                {canCreate && (
                    <Button type="button" variant="outline" onClick={openCreate}>
                        <Plus className="size-4" aria-hidden="true" />
                        Add window
                    </Button>
                )}
            </div>

            {rules.length === 0 ? (
                <p className="px-4 py-6 text-sm text-surface-500 dark:text-surface-400">
                    No weekly window is configured, so every day starts closed until an exception
                    opens it.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Weekly availability windows</caption>
                        <thead>
                            <tr className="text-left text-xs font-semibold text-surface-500 dark:text-surface-400">
                                <th scope="col" className="px-4 py-2">
                                    Day
                                </th>
                                <th scope="col" className="px-4 py-2">
                                    Window
                                </th>
                                <th scope="col" className="px-4 py-2 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rules.map((rule) => (
                                <tr
                                    key={rule.uuid}
                                    className="border-t border-surface-100 dark:border-surface-800"
                                >
                                    <td className="px-4 py-2 font-medium text-surface-800 dark:text-surface-100">
                                        {rule.day_label}
                                    </td>
                                    <td className="px-4 py-2 tabular-nums text-surface-600 dark:text-surface-300">
                                        {rule.start_time}–{rule.end_time}
                                        {!rule.is_available && (
                                            <Tag variant="outline" className="ml-2">
                                                Closed
                                            </Tag>
                                        )}
                                    </td>
                                    <td className="px-4 py-2">
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Edit ${rule.day_label} window`}
                                                    onClick={() => openEdit(rule)}
                                                >
                                                    <Pencil className="size-4" aria-hidden="true" />
                                                </Button>
                                            )}
                                            {canDelete && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Delete ${rule.day_label} window`}
                                                    onClick={() => setPendingDelete(rule)}
                                                >
                                                    <Trash2 className="size-4" aria-hidden="true" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/*
             * A window delete is soft (`AvailabilityRule` uses SoftDeletes) and
             * the restore endpoint already existed, so hiding the trashed rows
             * made the dialog's "you can add the window again" the only path
             * back. The list is short by nature (7 days), so it needs no table.
             */}
            {canRestore && trashedRules.length > 0 && (
                <div className="border-t border-surface-200 px-4 py-3 dark:border-surface-800">
                    <h3 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        Deleted weekly windows
                    </h3>
                    <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                        These weekdays stay closed until a window is restored.
                    </p>

                    {restoreError !== null && (
                        <p
                            role="alert"
                            className="mt-2 rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                        >
                            {restoreError}
                        </p>
                    )}

                    <ul className="mt-2 flex flex-col gap-1.5">
                        {trashedRules.map((rule) => (
                            <li
                                key={rule.uuid}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-surface-200 px-3 py-2 text-sm dark:border-surface-700"
                            >
                                <span className="flex flex-wrap items-center gap-2 text-surface-600 dark:text-surface-300">
                                    <span className="font-medium text-surface-800 dark:text-surface-100">
                                        {rule.day_label}
                                    </span>
                                    <span className="tabular-nums">
                                        {rule.start_time}–{rule.end_time}
                                    </span>
                                    <Tag variant="destructive">Deleted</Tag>
                                </span>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={restoreRule.isPending}
                                    onClick={() => restore(rule)}
                                >
                                    <RotateCcw className="size-3.5" aria-hidden="true" />
                                    Restore
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <RuleDialog
                key={editing?.uuid ?? 'new'}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                rule={editing}
                onSaved={onChanged}
            />

            <CrudDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                        setDeleteError(null);
                    }
                }}
                title="Delete this weekly window?"
                description={
                    pendingDelete === null
                        ? undefined
                        : `${pendingDelete.day_label} ${pendingDelete.start_time}–${pendingDelete.end_time} stops opening that weekday.`
                }
                confirmLabel="Delete window"
                confirmVariant="destructive"
                onConfirm={confirmDelete}
                loading={deleteRule.isPending}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    Exceptions and appointments are not changed. You can add the window again.
                </p>
                {deleteError !== null && (
                    <p
                        role="alert"
                        className="mt-2 rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                    >
                        {deleteError}
                    </p>
                )}
            </CrudDialog>
        </section>
    );
}
