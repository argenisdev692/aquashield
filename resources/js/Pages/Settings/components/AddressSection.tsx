/**
 * AddressSection — the US address block on the profile settings screen.
 *
 * The street line is the only editable autocomplete input. Selecting a Google
 * suggestion fills city / state / ZIP / country and the coordinates, then locks
 * those derived fields so a stray keystroke cannot silently de-sync them from
 * the verified place. Each locked field has a pencil button for a deliberate
 * manual override; `address_2` is always manual and is never touched by
 * autocomplete.
 */
import { useId, useState } from 'react';
import type { InertiaForm } from '@inertiajs/react';
import { CheckCircle2, Lock, MapPin, Pencil } from 'lucide-react';
import TextField from '@/Components/Forms/TextField';
import AddressAutocompleteField from '@/Components/Forms/AddressAutocompleteField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Tag } from '@/Components/ui/tag';
import { cn } from '@/lib/utils';
import type { AddressSelection } from '@/lib/google-maps';
import type { AddressField, ProfileAddressForm } from '@/Modules/Users/schemas/profileSettings';

interface AddressSectionProps {
    apiKey: string;
    form: InertiaForm<ProfileAddressForm>;
    errors: Partial<Record<string, string>>;
    onBlur: (field: keyof ProfileAddressForm) => void;
}

interface LockedFieldProps {
    label: string;
    name: AddressField;
    value: string;
    error?: string;
    onUnlock: () => void;
}

function LockedField({ label, name, value, error, onUnlock }: LockedFieldProps) {
    const uid = useId();
    const id = `${name}-${uid}`;
    const errorId = `${id}-error`;

    return (
        <div className="flex flex-col gap-1.5">
            <label
                htmlFor={id}
                className="text-sm font-medium text-surface-800 dark:text-surface-100"
            >
                {label}
            </label>

            <div className="flex items-center gap-2">
                <Input
                    id={id}
                    name={name}
                    value={value}
                    readOnly
                    aria-readonly="true"
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    className={cn(
                        'bg-surface-50 text-surface-600 dark:bg-surface-950/60 dark:text-surface-300',
                        error && 'border-aq-danger',
                    )}
                />

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    aria-label={`Edit ${label.toLowerCase()}`}
                    onClick={onUnlock}
                >
                    <Pencil className="size-3.5" aria-hidden="true" />
                </Button>
            </div>

            {error && (
                <p id={errorId} role="alert" className="text-xs font-medium text-aq-danger-strong">
                    {error}
                </p>
            )}
        </div>
    );
}

const LOCKED_FIELDS: readonly { field: AddressField; label: string }[] = [
    { field: 'city', label: 'City' },
    { field: 'state', label: 'State' },
    { field: 'zip_code', label: 'ZIP code' },
    { field: 'country', label: 'Country' },
];

export default function AddressSection({ apiKey, form, errors, onBlur }: AddressSectionProps) {
    const [unlocked, setUnlocked] = useState<ReadonlySet<AddressField>>(new Set());
    const [lastUnlocked, setLastUnlocked] = useState<AddressField | null>(null);
    const [autofilled, setAutofilled] = useState(false);

    const hasCoordinates = form.data.latitude !== null && form.data.longitude !== null;

    /*
     * Only a Google-verified address locks its derived fields. A manually
     * typed street line with no point keeps them editable, otherwise the first
     * keystroke would trap the user behind four pencil buttons.
     */
    const isLocked = (field: AddressField): boolean =>
        hasCoordinates &&
        form.data.address.trim() !== '' &&
        form.data[field].trim() !== '' &&
        !unlocked.has(field);

    const unlock = (field: AddressField): void => {
        setLastUnlocked(field);
        setUnlocked((current) => new Set(current).add(field));
    };

    const lock = (field: AddressField): void => {
        setLastUnlocked((current) => (current === field ? null : current));
        setUnlocked((current) => {
            const next = new Set(current);
            next.delete(field);
            return next;
        });
    };

    const onAddressInput = (next: string): void => {
        // A manually edited street line no longer matches the stored point.
        form.setData({ address: next, latitude: null, longitude: null });
        setAutofilled(false);
    };

    const onAddressSelect = (selection: AddressSelection): void => {
        form.setData({
            address: selection.address,
            zip_code: selection.zip_code,
            city: selection.city,
            state: selection.state,
            country: selection.country,
            latitude: selection.latitude,
            longitude: selection.longitude,
        });
        setUnlocked(new Set());
        setLastUnlocked(null);
        setAutofilled(true);
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center gap-2">
                <Tag variant={hasCoordinates ? 'success' : 'outline'}>
                    <MapPin className="mr-1 size-3" aria-hidden="true" />
                    {hasCoordinates ? 'Google verified' : 'Manual entry'}
                </Tag>

                <p className="text-xs text-surface-600 dark:text-surface-300">
                    Autocomplete covers US addresses only.
                </p>
            </div>

            {apiKey.trim() === '' && (
                <div
                    role="alert"
                    className="rounded-lg border border-aq-warning/40 bg-aq-warning/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                >
                    Add <code className="font-mono text-xs">GOOGLE_MAPS_API_KEY</code> to enable
                    autocomplete. You can still enter the address manually.
                </div>
            )}

            <AddressAutocompleteField
                label="Street address"
                name="address"
                value={form.data.address}
                onChange={onAddressInput}
                onAddressSelect={onAddressSelect}
                onBlur={() => onBlur('address')}
                apiKey={apiKey}
                error={errors.address}
                hint="Start typing, then choose a suggestion. The city, state, ZIP and country fill automatically."
                placeholder="123 Main St"
            />

            {autofilled && (
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-lg border border-aq-success/30 bg-aq-success/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                >
                    <CheckCircle2
                        className="mt-0.5 size-4 shrink-0 text-aq-success"
                        aria-hidden="true"
                    />
                    Address details filled from Google. Apartment or suite stays manual in address
                    line 2.
                </p>
            )}

            <TextField
                label="Address line 2"
                name="address_2"
                value={form.data.address_2}
                onChange={(value) => form.setData('address_2', value)}
                onBlur={() => onBlur('address_2')}
                error={errors.address_2}
                hint="Apartment, suite, unit or building. This field is never overwritten by autocomplete."
                autocomplete="address-line2"
                placeholder="Apt 4B"
            />

            <div className="grid gap-4 sm:grid-cols-2">
                {LOCKED_FIELDS.map(({ field, label }) =>
                    isLocked(field) ? (
                        <LockedField
                            key={field}
                            label={label}
                            name={field}
                            value={form.data[field]}
                            error={errors[field]}
                            onUnlock={() => unlock(field)}
                        />
                    ) : (
                        <div key={field} className="flex flex-col gap-1.5">
                            <TextField
                                label={label}
                                name={field}
                                value={form.data[field]}
                                onChange={(value) => form.setData(field, value)}
                                onBlur={() => onBlur(field)}
                                error={errors[field]}
                                autocomplete={
                                    field === 'zip_code'
                                        ? 'postal-code'
                                        : field === 'city'
                                          ? 'address-level2'
                                          : field === 'state'
                                            ? 'address-level1'
                                            : 'country-name'
                                }
                                autoFocus={lastUnlocked === field}
                            />

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="self-start"
                                onClick={() => lock(field)}
                            >
                                <Lock className="mr-1.5 size-3" aria-hidden="true" />
                                Lock field
                            </Button>
                        </div>
                    ),
                )}
            </div>

            {hasCoordinates && (
                <p className="text-xs text-surface-500 dark:text-surface-300">
                    Coordinates: {form.data.latitude?.toFixed(5)}, {form.data.longitude?.toFixed(5)}
                </p>
            )}

            {unlocked.size > 0 && (
                <div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setUnlocked(new Set());
                            setLastUnlocked(null);
                        }}
                    >
                        <Lock className="mr-1.5 size-3" aria-hidden="true" />
                        Lock all address fields
                    </Button>
                </div>
            )}
        </div>
    );
}
