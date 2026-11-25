/**
 * The appointment fields shared by the create page and the dashboard dialog.
 * The lead is a picker on a fresh booking and a read-only summary when the
 * dialog was opened from a lead card.
 */
import CheckboxField from '@/Components/Forms/CheckboxField';
import DateField from '@/Components/Forms/DateField';
import NumberField from '@/Components/Forms/NumberField';
import SelectField from '@/Components/Forms/SelectField';
import TextField from '@/Components/Forms/TextField';
import TextareaField from '@/Components/Forms/TextareaField';
import TimeField from '@/Components/Forms/TimeField';
import LeadPicker from './LeadPicker';
import AvailabilityNotice from './AvailabilityNotice';
import type { FormErrorMap } from '@/lib/forms';
import { regionLabel } from '@/lib/regions';
import type { AppointmentFormValues } from '../schemas/appointment';

type Option = { value: string; label: string };

export type AppointmentOptions = {
    kinds: Option[];
    regions: Option[];
};

interface AppointmentFormFieldsProps {
    values: AppointmentFormValues;
    errors: FormErrorMap;
    options: AppointmentOptions;
    onChange: (values: AppointmentFormValues) => void;
    onBlur: (key: keyof AppointmentFormValues) => void;
    disabled?: boolean;
    /** Region timezone the server parses the wall-clock time in. */
    timezone?: string;
    /** A lead card opened the dialog: the lead is already chosen. */
    fixedLead?: { name: string; phone: string | null; region: string | null } | null;
}

export default function AppointmentFormFields({
    values,
    errors,
    options,
    onChange,
    onBlur,
    disabled = false,
    timezone,
    fixedLead = null,
}: AppointmentFormFieldsProps) {
    const update = (patch: Partial<AppointmentFormValues>): void => {
        onChange({ ...values, ...patch });
    };

    return (
        <div className="flex flex-col gap-4">
            {fixedLead === null ? (
                <LeadPicker
                    value={values.lead_uuid}
                    onChange={(uuid) => update({ lead_uuid: uuid })}
                    onBlur={() => onBlur('lead_uuid')}
                    error={errors.lead_uuid}
                    required
                    disabled={disabled}
                    hint="Search the lead by name, phone or email."
                />
            ) : (
                <div className="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 dark:border-surface-700 dark:bg-surface-800/40">
                    <p className="text-xs font-medium text-surface-500 dark:text-surface-400">
                        Lead
                    </p>
                    <p className="mt-0.5 text-sm font-semibold text-surface-900 dark:text-surface-0">
                        {fixedLead.name}
                    </p>
                    {fixedLead.phone !== null && (
                        <p className="text-xs tabular-nums text-surface-500 dark:text-surface-400">
                            {fixedLead.phone}
                        </p>
                    )}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <SelectField
                    label="Visit kind"
                    name="kind"
                    value={values.kind}
                    onChange={(value) =>
                        update({
                            kind:
                                value === 'inspection' ||
                                value === 'estimate' ||
                                value === 'adjuster_meeting' ||
                                value === 'job_visit' ||
                                value === 'follow_up'
                                    ? value
                                    : 'inspection',
                        })
                    }
                    onBlur={() => onBlur('kind')}
                    error={errors.kind}
                    options={options.kinds}
                    disabled={disabled}
                />

                {fixedLead?.region ? (
                    <TextField
                        label="Region"
                        name="region_display"
                        value={regionLabel(fixedLead.region)}
                        onChange={() => {}}
                        readOnly
                        disabled={disabled}
                        hint="Derived from the lead's address."
                    />
                ) : (
                    <SelectField
                        label="Region"
                        name="region"
                        value={values.region}
                        onChange={(value) =>
                            update({
                                region:
                                    value === 'houston' || value === 'dallas' || value === 'florida'
                                        ? value
                                        : '',
                            })
                        }
                        onBlur={() => onBlur('region')}
                        error={errors.region}
                        options={options.regions}
                        placeholder="Use the lead's region"
                        disabled={disabled}
                    />
                )}

                <div className="flex flex-col gap-2">
                    <DateField
                        label="Date"
                        name="date"
                        value={values.date === '' ? null : values.date}
                        onChange={(value) => update({ date: value ?? '' })}
                        onBlur={() => onBlur('date')}
                        error={errors.date ?? errors.starts_at}
                        required
                        disabled={disabled}
                    />

                    <AvailabilityNotice
                        date={values.date}
                        region={fixedLead?.region ?? (values.region === '' ? null : values.region)}
                        onPickDate={(date) => update({ date })}
                    />
                </div>

                <TimeField
                    label="Start time"
                    name="time"
                    value={values.time}
                    onChange={(value) => update({ time: value })}
                    onBlur={() => onBlur('time')}
                    error={errors.time ?? errors.starts_at}
                    hint={
                        timezone === undefined
                            ? 'Read in the appointment region timezone.'
                            : `Read in the appointment region timezone (default ${timezone}).`
                    }
                    required
                    disabled={disabled}
                />

                <NumberField
                    label="Duration"
                    name="duration_minutes"
                    value={values.duration_minutes}
                    onChange={(value) => update({ duration_minutes: value ?? 15 })}
                    onBlur={() => onBlur('duration_minutes')}
                    error={errors.duration_minutes}
                    min={15}
                    max={480}
                    step={15}
                    suffix="minutes"
                    required
                    disabled={disabled}
                    hint="The office's travel buffer is applied on top of this window."
                />
            </div>

            <CheckboxField
                label="Emergency — book outside the normal daily cap"
                name="is_emergency"
                checked={values.is_emergency}
                onChange={(checked) => update({ is_emergency: checked })}
                onBlur={() => onBlur('is_emergency')}
                error={errors.is_emergency}
                disabled={disabled}
            />

            <TextareaField
                label="Notes"
                name="notes"
                value={values.notes}
                onChange={(value) => update({ notes: value })}
                onBlur={() => onBlur('notes')}
                error={errors.notes}
                rows={3}
                placeholder="Gate code, pets, access instructions…"
                disabled={disabled}
            />
        </div>
    );
}
