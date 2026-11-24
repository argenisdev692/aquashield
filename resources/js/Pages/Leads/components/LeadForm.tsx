/**
 * The lead create/edit fields. Two pages share one contract, so a new field is
 * added once and both transports (create and update) stay in step.
 */
import AddressAutocompleteField from '@/Components/Forms/AddressAutocompleteField';
import CheckboxField from '@/Components/Forms/CheckboxField';
import DateField from '@/Components/Forms/DateField';
import PhoneField from '@/Components/Forms/PhoneField';
import SelectField from '@/Components/Forms/SelectField';
import TextField from '@/Components/Forms/TextField';
import TextareaField from '@/Components/Forms/TextareaField';
import type { FormErrorMap } from '@/lib/forms';
import type { AddressSelection } from '@/lib/google-maps';
import { PERSON_NAME_MAX, filterPersonNameInput, normalizePersonName } from '@/lib/names';
import { regionForAddress, regionLabel } from '@/lib/regions';
import type { LeadFormValues } from '@/Modules/LeadAppointment/schemas/lead';

type Option = { value: string; label: string };

/** Only the catalogs this form renders; a page may pass the wider option set. */
export type LeadFormOptions = {
    regions: Option[];
    owners: Modules.LeadAppointment.Application.Dtos.AssignableOwnerData[];
};

interface LeadFormProps {
    values: LeadFormValues;
    errors: FormErrorMap;
    options: LeadFormOptions;
    mode: 'create' | 'edit';
    /** Browser key from the server page props. Public by design. */
    googleMapsApiKey: string;
    /** Receives the whole next state; Inertia's `setData` owns the object form. */
    onChange: (values: LeadFormValues) => void;
    onBlur: (key: keyof LeadFormValues) => void;
    /**
     * The dashboard quick-add is an intake form, not the full record editor:
     * it hides the office-only language preference and defaults it to English.
     */
    showPreferredLanguage?: boolean;
    /**
     * Most leads are homeowners, so the quick-add asks for the person only.
     * The full record editor still exposes Company for commercial, referral
     * and Meta/website leads that carry one.
     */
    showCompany?: boolean;
}

const sectionTitle = 'text-sm font-semibold text-surface-900 dark:text-surface-0';

export default function LeadForm({
    values,
    errors,
    options,
    mode,
    googleMapsApiKey,
    onChange,
    onBlur,
    showPreferredLanguage = true,
    showCompany = true,
}: LeadFormProps) {
    const update = (patch: Partial<LeadFormValues>): void => {
        onChange({ ...values, ...patch });
    };

    /*
     * Google returns the street line only. Address 2 is deliberately absent:
     * apartment, suite and unit numbers stay manual, exactly as the field's
     * contract documents.
     */
    const onAddressSelect = (selection: AddressSelection): void => {
        update({
            address: selection.address,
            city: selection.city === '' ? values.city : selection.city,
            state:
                selection.state === 'TX' || selection.state === 'FL'
                    ? selection.state
                    : values.state,
            zip_code: selection.zip_code === '' ? values.zip_code : selection.zip_code,
            region: regionForAddress(selection.state, selection.city, selection.zip_code),
            latitude: selection.latitude,
            longitude: selection.longitude,
        });
    };

    return (
        <div className="flex flex-col gap-8">
            <section className="grid gap-4 md:grid-cols-2">
                <h2 className={`${sectionTitle} md:col-span-2`}>Contact</h2>

                <TextField
                    label="First name"
                    name="first_name"
                    value={values.first_name}
                    onChange={(value) => update({ first_name: filterPersonNameInput(value) })}
                    onBlur={() => {
                        update({ first_name: normalizePersonName(values.first_name) });
                        onBlur('first_name');
                    }}
                    error={errors.first_name}
                    required
                    autoFocus
                    autocomplete="given-name"
                    autoCapitalize="words"
                    maxLength={PERSON_NAME_MAX}
                />

                <TextField
                    label="Last name"
                    name="last_name"
                    value={values.last_name}
                    onChange={(value) => update({ last_name: filterPersonNameInput(value) })}
                    onBlur={() => {
                        update({ last_name: normalizePersonName(values.last_name) });
                        onBlur('last_name');
                    }}
                    error={errors.last_name}
                    required
                    autocomplete="family-name"
                    autoCapitalize="words"
                    maxLength={PERSON_NAME_MAX}
                />

                {showCompany && (
                    <TextField
                        label="Company"
                        name="company_name"
                        value={values.company_name}
                        onChange={(value) => update({ company_name: value })}
                        onBlur={() => onBlur('company_name')}
                        error={errors.company_name}
                        maxLength={255}
                    />
                )}

                <div className={showCompany ? undefined : 'md:col-span-2'}>
                    <TextField
                        label="Email"
                        name="email"
                        type="email"
                        value={values.email}
                        onChange={(value) => update({ email: value })}
                        onBlur={() => onBlur('email')}
                        error={errors.email}
                        hint="Email or phone is required."
                        autocomplete="email"
                        maxLength={255}
                    />
                </div>

                <div className={showPreferredLanguage ? undefined : 'md:col-span-2'}>
                    <PhoneField
                        label="Phone"
                        name="phone"
                        value={values.phone}
                        onChange={(value) => update({ phone: value })}
                        onBlur={() => onBlur('phone')}
                        error={errors.phone}
                        country="US"
                    />
                </div>

                {showPreferredLanguage && (
                    <SelectField
                        label="Preferred language"
                        name="preferred_language"
                        value={values.preferred_language}
                        onChange={(value) =>
                            update({ preferred_language: value === 'es' ? 'es' : 'en' })
                        }
                        onBlur={() => onBlur('preferred_language')}
                        error={errors.preferred_language}
                        options={[
                            { value: 'en', label: 'English' },
                            { value: 'es', label: 'Spanish' },
                        ]}
                    />
                )}
            </section>

            <section className="grid gap-4 md:grid-cols-2">
                <h2 className={`${sectionTitle} md:col-span-2`}>Property</h2>

                <AddressAutocompleteField
                    label="Address"
                    name="address"
                    value={values.address}
                    onChange={(value) =>
                        update({ address: value, latitude: null, longitude: null })
                    }
                    onAddressSelect={onAddressSelect}
                    onBlur={() => onBlur('address')}
                    error={errors.address}
                    apiKey={googleMapsApiKey}
                    hint="Street address only. Apartment and suite go on line 2."
                />

                <TextField
                    label="Address line 2"
                    name="address_2"
                    value={values.address_2}
                    onChange={(value) => update({ address_2: value })}
                    onBlur={() => onBlur('address_2')}
                    error={errors.address_2}
                    maxLength={255}
                />

                <TextField
                    label="City"
                    name="city"
                    value={values.city}
                    onChange={(value) =>
                        update({
                            city: value,
                            region: regionForAddress(values.state, value, values.zip_code),
                        })
                    }
                    onBlur={() => onBlur('city')}
                    error={errors.city}
                    autocomplete="address-level2"
                    maxLength={120}
                />

                <TextField
                    label="Region"
                    name="region"
                    value={regionLabel(values.region)}
                    onChange={() => {}}
                    readOnly
                    placeholder="Not determined"
                    hint="Derived from State and ZIP. Change the address to correct it."
                />

                <div className="grid grid-cols-2 gap-4 md:col-span-2">
                    <TextField
                        label="State"
                        name="state"
                        value={values.state}
                        onChange={(value) => {
                            const next = value
                                .toUpperCase()
                                .replace(/[^A-Z]/g, '')
                                .slice(0, 2);

                            if (next === '' || next === 'TX' || next === 'FL') {
                                update({ state: next });
                            }
                        }}
                        onBlur={() => onBlur('state')}
                        error={errors.state}
                        readOnly
                        autocomplete="address-level1"
                        maxLength={2}
                        hint="Filled from the selected address."
                    />

                    <TextField
                        label="ZIP"
                        name="zip_code"
                        value={values.zip_code}
                        onChange={(value) =>
                            update({
                                zip_code: value,
                                region: regionForAddress(values.state, values.city, value),
                            })
                        }
                        onBlur={() => onBlur('zip_code')}
                        error={errors.zip_code}
                        inputMode="numeric"
                        autocomplete="postal-code"
                        maxLength={10}
                    />
                </div>
            </section>

            <section className="grid gap-4 md:grid-cols-2">
                <h2 className={`${sectionTitle} md:col-span-2`}>Qualification</h2>

                <div className="flex flex-col gap-3 md:col-span-2">
                    <CheckboxField
                        label="Emergency — active water intrusion or unsafe property"
                        name="is_emergency"
                        checked={values.is_emergency}
                        onChange={(checked) => update({ is_emergency: checked })}
                        onBlur={() => onBlur('is_emergency')}
                        error={errors.is_emergency}
                    />

                    <CheckboxField
                        label="Customer has insurance"
                        name="insurance_property"
                        checked={values.insurance_property}
                        onChange={(checked) =>
                            update({
                                insurance_property: checked,
                                ...(checked ? {} : { insurance_carrier: '', claim_number: '' }),
                            })
                        }
                        onBlur={() => onBlur('insurance_property')}
                        error={errors.insurance_property}
                        hint={
                            mode === 'edit'
                                ? 'Turn this on before recording the carrier or claim number.'
                                : undefined
                        }
                    />

                    <CheckboxField
                        label="Customer intends to file an insurance claim"
                        name="intent_to_claim"
                        checked={values.intent_to_claim}
                        onChange={(checked) => update({ intent_to_claim: checked })}
                        onBlur={() => onBlur('intent_to_claim')}
                        error={errors.intent_to_claim}
                    />
                </div>

                {mode === 'edit' && values.insurance_property && (
                    <>
                        <TextField
                            label="Insurance carrier"
                            name="insurance_carrier"
                            value={values.insurance_carrier}
                            onChange={(value) => update({ insurance_carrier: value })}
                            onBlur={() => onBlur('insurance_carrier')}
                            error={errors.insurance_carrier}
                            maxLength={255}
                        />

                        <TextField
                            label="Claim number"
                            name="claim_number"
                            value={values.claim_number}
                            onChange={(value) => update({ claim_number: value })}
                            onBlur={() => onBlur('claim_number')}
                            error={errors.claim_number}
                            maxLength={100}
                        />
                    </>
                )}

                <TextareaField
                    label="Damage description"
                    name="damage_description"
                    value={values.damage_description}
                    onChange={(value) => update({ damage_description: value })}
                    onBlur={() => onBlur('damage_description')}
                    error={errors.damage_description}
                    rows={3}
                    placeholder="What happened, which rooms, when it started…"
                />

                <TextareaField
                    label="Notes"
                    name="notes"
                    value={values.notes}
                    onChange={(value) => update({ notes: value })}
                    onBlur={() => onBlur('notes')}
                    error={errors.notes}
                    rows={3}
                    placeholder="Internal notes for the office"
                />
            </section>

            {mode === 'edit' && (
                <section className="grid gap-4 md:grid-cols-2">
                    <h2 className={`${sectionTitle} md:col-span-2`}>Inspection</h2>

                    <DateField
                        label="Follow-up date"
                        name="follow_up_date"
                        value={values.follow_up_date === '' ? null : values.follow_up_date}
                        onChange={(value) => update({ follow_up_date: value ?? '' })}
                        onBlur={() => onBlur('follow_up_date')}
                        error={errors.follow_up_date}
                    />

                    <DateField
                        label="Inspection date"
                        name="inspection_date"
                        value={values.inspection_date === '' ? null : values.inspection_date}
                        onChange={(value) => update({ inspection_date: value ?? '' })}
                        onBlur={() => onBlur('inspection_date')}
                        error={errors.inspection_date}
                    />

                    <TextField
                        label="Inspection time"
                        name="inspection_time"
                        value={values.inspection_time}
                        onChange={(value) => update({ inspection_time: value })}
                        onBlur={() => onBlur('inspection_time')}
                        error={errors.inspection_time}
                        hint="24-hour HH:MM"
                        inputMode="numeric"
                        placeholder="09:00"
                        maxLength={5}
                    />

                    <SelectField
                        label="Inspection status"
                        name="inspection_status"
                        value={values.inspection_status}
                        onChange={(value) =>
                            update({
                                inspection_status:
                                    value === 'pending' ||
                                    value === 'confirmed' ||
                                    value === 'completed' ||
                                    value === 'declined'
                                        ? value
                                        : '',
                            })
                        }
                        onBlur={() => onBlur('inspection_status')}
                        error={errors.inspection_status}
                        options={[
                            { value: 'pending', label: 'Pending' },
                            { value: 'confirmed', label: 'Confirmed' },
                            { value: 'completed', label: 'Completed' },
                            { value: 'declined', label: 'Declined' },
                        ]}
                        placeholder="Not set"
                    />

                    <TextareaField
                        label="Additional note"
                        name="additional_note"
                        value={values.additional_note}
                        onChange={(value) => update({ additional_note: value })}
                        onBlur={() => onBlur('additional_note')}
                        error={errors.additional_note}
                        rows={2}
                        placeholder="Gate code, pets, access instructions…"
                    />
                </section>
            )}

            <section className="grid gap-4 md:grid-cols-2">
                <h2 className={`${sectionTitle} md:col-span-2`}>Assignment</h2>

                <SelectField
                    label="Owner"
                    name="owner_uuid"
                    value={values.owner_uuid === '' ? null : values.owner_uuid}
                    onChange={(value) => update({ owner_uuid: value ?? '' })}
                    onBlur={() => onBlur('owner_uuid')}
                    error={errors.owner_uuid}
                    options={options.owners.map((owner) => ({
                        value: owner.uuid,
                        label: owner.name,
                    }))}
                    placeholder="Unassigned"
                    hint="The person responsible for the next contact."
                />
            </section>
        </div>
    );
}
