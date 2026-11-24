/**
 * CompanyAddressPanel — the US address block of the company profile.
 *
 * Only the street line is autocompleted. Choosing a Google suggestion fills
 * city, state, ZIP, country and the coordinates, then locks those derived
 * fields so a stray keystroke cannot silently de-sync the record from the
 * verified place. Address line 2 is always typed by hand and is never touched
 * by a suggestion.
 *
 * A derived field with a stored value starts locked, so state / city / ZIP are
 * read-only on load even when the record was created before coordinates were
 * stored. The unlock pencils are rendered only for a capability holder
 * (`canUpdate`, which this project grants to SUPER_ADMIN); a view-only visitor
 * sees every control disabled and has no way to break the match.
 */
import { useId, useState } from 'react';
import type { InertiaForm } from '@inertiajs/react';
import { CheckCircle2, Lock, MapPin, Pencil } from 'lucide-react';
import AddressAutocompleteField from '@/Components/Forms/AddressAutocompleteField';
import FormSection from '@/Components/Forms/FormSection';
import TextField from '@/Components/Forms/TextField';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Tag } from '@/Components/ui/tag';
import { cn } from '@/lib/utils';
import type { AddressSelection } from '@/lib/google-maps';
import type { CompanyProfileForm } from '@/Modules/CompanyData/schemas/companyProfile';

type AddressField = 'city' | 'state' | 'zip_code' | 'country';

interface CompanyAddressPanelProps {
    apiKey: string;
    form: InertiaForm<CompanyProfileForm>;
    errors: Partial<Record<string, string>>;
    onBlur: (field: keyof CompanyProfileForm) => void;
    canUpdate: boolean;
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

const LOCKED_FIELDS: readonly { field: AddressField; label: string; autocomplete: string }[] = [
    { field: 'city', label: 'City', autocomplete: 'address-level2' },
    { field: 'state', label: 'State', autocomplete: 'address-level1' },
    { field: 'zip_code', label: 'ZIP code', autocomplete: 'postal-code' },
    { field: 'country', label: 'Country', autocomplete: 'country-name' },
];

const coordinate = (value: number | null): string => (value === null ? '' : String(value));

export default function CompanyAddressPanel({
    apiKey,
    form,
    errors,
    onBlur,
    canUpdate,
}: CompanyAddressPanelProps) {
    /*
     * Initial lock set: a derived field that already holds a value is treated
     * as server-verified and starts read-only. Empty fields stay editable so a
     * new address never starts behind four pencil buttons.
     */
    const [locked, setLocked] = useState<ReadonlySet<AddressField>>(() => {
        if (form.data.address.trim() === '') {
            return new Set();
        }

        return new Set(
            LOCKED_FIELDS.filter(({ field }) => form.data[field].trim() !== '').map(
                ({ field }) => field,
            ),
        );
    });

    const [lastUnlocked, setLastUnlocked] = useState<AddressField | null>(null);
    const [override, setOverride] = useState(false);
    const [autofilled, setAutofilled] = useState(false);

    const hasCoordinates = form.data.latitude.trim() !== '' && form.data.longitude.trim() !== '';

    const isLocked = (field: AddressField): boolean => canUpdate && locked.has(field);

    const isUnlockedWithValue = (field: AddressField): boolean =>
        canUpdate && !locked.has(field) && form.data[field].trim() !== '';

    const overrideActive =
        override && LOCKED_FIELDS.some(({ field }) => isUnlockedWithValue(field));

    const unlock = (field: AddressField): void => {
        setLastUnlocked(field);
        setOverride(true);
        setLocked((current) => {
            const next = new Set(current);
            next.delete(field);
            return next;
        });
    };

    const lock = (field: AddressField): void => {
        setLastUnlocked((current) => (current === field ? null : current));
        setLocked((current) => new Set(current).add(field));
    };

    const lockAll = (): void => {
        setLocked(
            new Set(
                LOCKED_FIELDS.filter(({ field }) => form.data[field].trim() !== '').map(
                    ({ field }) => field,
                ),
            ),
        );
        setLastUnlocked(null);
        setOverride(false);
    };

    const onAddressInput = (next: string): void => {
        // A manually edited street line no longer matches the stored point.
        form.setData({ address: next, latitude: '', longitude: '' });
        setAutofilled(false);
    };

    const onAddressSelect = (selection: AddressSelection): void => {
        const derived: Record<AddressField, string> = {
            city: selection.city,
            state: selection.state,
            zip_code: selection.zip_code,
            country: selection.country === '' ? 'United States' : selection.country,
        };

        form.setData({
            address: selection.address,
            ...derived,
            country_code: 'US',
            latitude: coordinate(selection.latitude),
            longitude: coordinate(selection.longitude),
        });

        setLocked(
            new Set(
                LOCKED_FIELDS.filter(({ field }) => derived[field].trim() !== '').map(
                    ({ field }) => field,
                ),
            ),
        );
        setLastUnlocked(null);
        setOverride(false);
        setAutofilled(true);
    };

    return (
        <FormSection
            title="Company address"
            description="The address used on invoices, contracts and service documents."
            icon={<MapPin className="size-4" aria-hidden="true" />}
            columns={1}
        >
            <div className="flex flex-wrap items-center gap-2">
                <Tag variant={hasCoordinates ? 'success' : 'outline'}>
                    <MapPin className="mr-1 size-3" aria-hidden="true" />
                    {hasCoordinates ? 'Google verified' : 'Manual entry'}
                </Tag>

                <p className="text-xs text-surface-600 dark:text-surface-300">
                    Autocomplete covers US addresses only. City, state, ZIP and country fill from
                    the suggestion and stay read-only.
                </p>
            </div>

            {canUpdate && apiKey.trim() === '' && (
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
                hint="Start typing, then choose a suggestion. Address line 2 stays manual."
                placeholder="123 Main St"
                disabled={!canUpdate}
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
                disabled={!canUpdate}
            />

            <div className="grid gap-4 sm:grid-cols-2">
                {LOCKED_FIELDS.map(({ field, label, autocomplete }) =>
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
                                onChange={(value) =>
                                    field === 'country'
                                        ? form.setData({ country: value, country_code: '' })
                                        : form.setData(field, value)
                                }
                                onBlur={() => onBlur(field)}
                                error={errors[field]}
                                autocomplete={autocomplete}
                                disabled={!canUpdate}
                                autoFocus={lastUnlocked === field}
                            />

                            {canUpdate && form.data[field].trim() !== '' && (
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
                            )}
                        </div>
                    ),
                )}
            </div>

            {hasCoordinates && (
                <p className="text-xs text-surface-500 dark:text-surface-400">
                    Coordinates: {form.data.latitude}, {form.data.longitude}
                </p>
            )}

            {overrideActive && (
                <div className="flex flex-wrap items-center gap-2">
                    <Tag variant="warning">Super admin override</Tag>

                    <Button type="button" variant="ghost" size="sm" onClick={lockAll}>
                        <Lock className="mr-1.5 size-3" aria-hidden="true" />
                        Lock all address fields
                    </Button>
                </div>
            )}
        </FormSection>
    );
}
