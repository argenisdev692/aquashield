/**
 * The availability day fields shared by Create and Edit.
 *
 * The same form covers both directions: closing a day (crew cannot go) and
 * opening an extra day (a Saturday window, a holiday the crew works). The
 * server checks the one-active-row-per-date rule; the window is only required
 * when the day is open.
 */
import DateField from '@/Components/Forms/DateField';
import SwitchField from '@/Components/Forms/SwitchField';
import TextField from '@/Components/Forms/TextField';
import TimeField from '@/Components/Forms/TimeField';
import type { FormErrorMap } from '@/lib/forms';
import type { AvailabilityExceptionFormValues } from '@/Modules/LeadAppointment/schemas/availability';

interface ExceptionFormFieldsProps {
    values: AvailabilityExceptionFormValues;
    errors: FormErrorMap;
    onChange: (values: AvailabilityExceptionFormValues) => void;
    onBlur: (key: keyof AvailabilityExceptionFormValues) => void;
    disabled?: boolean;
    /** `holiday` came from the federal sync; editing it is possible but not durable. */
    source?: string | null;
}

export default function ExceptionFormFields({
    values,
    errors,
    onChange,
    onBlur,
    disabled = false,
    source = null,
}: ExceptionFormFieldsProps) {
    const update = (patch: Partial<AvailabilityExceptionFormValues>): void => {
        onChange({ ...values, ...patch });
    };

    return (
        <div className="flex flex-col gap-4">
            {source === 'holiday' && (
                <p
                    role="status"
                    className="rounded-lg border border-aq-warning/40 bg-aq-warning/5 px-3 py-2 text-sm text-surface-700 dark:text-surface-200"
                >
                    This day was created by the federal holiday sync. Editing it keeps the holiday
                    source, and the next sync can replace the reason.
                </p>
            )}

            <DateField
                label="Day"
                name="date"
                value={values.date === '' ? null : values.date}
                onChange={(value) => update({ date: value ?? '' })}
                onBlur={() => onBlur('date')}
                error={errors.date}
                hint="One row per date. A second active row for the same day is refused by the server."
                required
                disabled={disabled}
            />

            <SwitchField
                label="The crew can work this day"
                name="is_available"
                checked={values.is_available}
                onChange={(checked) => update({ is_available: checked })}
                onBlur={() => onBlur('is_available')}
                error={errors.is_available}
                description="Off closes the day even when the weekly hours say it is open."
                disabled={disabled}
            />

            {values.is_available && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <TimeField
                        label="From"
                        name="start_time"
                        value={values.start_time}
                        onChange={(value) => update({ start_time: value })}
                        onBlur={() => onBlur('start_time')}
                        error={errors.start_time}
                        required
                        disabled={disabled}
                    />

                    <TimeField
                        label="To"
                        name="end_time"
                        value={values.end_time}
                        onChange={(value) => update({ end_time: value })}
                        onBlur={() => onBlur('end_time')}
                        error={errors.end_time}
                        required
                        disabled={disabled}
                    />
                </div>
            )}

            <TextField
                label="Reason"
                name="reason"
                value={values.reason}
                onChange={(value) => update({ reason: value })}
                onBlur={() => onBlur('reason')}
                error={errors.reason}
                hint="Shown on the calendar and in the operator's day notice."
                placeholder="Crew training, holiday shutdown, storm day…"
                maxLength={255}
                disabled={disabled}
            />
        </div>
    );
}
