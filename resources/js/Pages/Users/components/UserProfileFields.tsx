/**
 * The shared identity/address section for Users/Create and Users/Edit.
 *
 * It is a pure field composition: the page owns the Inertia form and the
 * module hook owns the availability request. That keeps the two pages from
 * drifting while avoiding a second form engine.
 */
import AddressAutocompleteField from '@/Components/Forms/AddressAutocompleteField';
import DateField from '@/Components/Forms/DateField';
import PhoneField from '@/Components/Forms/PhoneField';
import SelectField from '@/Components/Forms/SelectField';
import TextField from '@/Components/Forms/TextField';
import type { AddressSelection } from '@/lib/google-maps';
import type { FormErrorMap } from '@/lib/forms';
import type { UpdateUserForm } from '@/Modules/Users/schemas/userProfile';
import type { FieldAvailability } from '@/Modules/Users/hooks/useUserAvailability';
import AvailabilityField from './AvailabilityField';

interface UserProfileFieldsProps {
    data: UpdateUserForm;
    errors: FormErrorMap;
    onChange: <K extends keyof UpdateUserForm>(key: K, value: UpdateUserForm[K]) => void;
    onBlur: (key: keyof UpdateUserForm) => void;
    availability: {
        username: FieldAvailability;
        email: FieldAvailability;
    };
    googleMapsApiKey?: string;
    disabled?: boolean;
}

/*
 * `max` for the date-of-birth input. Module scope keeps `new Date()` out of
 * render (an impure call React Compiler flags); a page session cannot outlive
 * the day in any way that matters.
 */
const TODAY_ISO = new Date().toISOString().slice(0, 10);

const GENDER_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'other', label: 'Other' },
];

export default function UserProfileFields({
    data,
    errors,
    onChange,
    onBlur,
    availability,
    googleMapsApiKey = '',
    disabled = false,
}: UserProfileFieldsProps) {
    const onAddressSelect = (selection: AddressSelection): void => {
        onChange('address', selection.address);
        onChange('zip_code', selection.zip_code);
        onChange('city', selection.city);
        onChange('state', selection.state);
        onChange('country', selection.country);
        onChange('latitude', selection.latitude);
        onChange('longitude', selection.longitude);
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <TextField
                label="First name"
                name="first_name"
                value={data.first_name}
                onChange={(value) => onChange('first_name', value)}
                onBlur={() => onBlur('first_name')}
                error={errors.first_name}
                required
                autocomplete="given-name"
                disabled={disabled}
            />

            <TextField
                label="Last name"
                name="last_name"
                value={data.last_name}
                onChange={(value) => onChange('last_name', value)}
                onBlur={() => onBlur('last_name')}
                error={errors.last_name}
                autocomplete="family-name"
                disabled={disabled}
            />

            <AvailabilityField
                label="Username"
                name="username"
                type="text"
                value={data.username}
                onChange={(value) => onChange('username', value)}
                onBlur={() => onBlur('username')}
                error={errors.username}
                hint="Optional. 3–20 characters, lowercase."
                autocomplete="username"
                availability={availability.username}
            />

            <AvailabilityField
                label="Email"
                name="email"
                type="email"
                value={data.email}
                onChange={(value) => onChange('email', value)}
                onBlur={() => onBlur('email')}
                error={errors.email}
                required
                autocomplete="email"
                availability={availability.email}
            />

            <PhoneField
                label="Phone"
                name="phone"
                value={data.phone}
                onChange={(value) => onChange('phone', value)}
                onBlur={() => onBlur('phone')}
                error={errors.phone}
                disabled={disabled}
            />

            <DateField
                label="Date of birth"
                name="date_of_birth"
                value={data.date_of_birth === '' ? null : data.date_of_birth}
                onChange={(value) => onChange('date_of_birth', value ?? '')}
                onBlur={() => onBlur('date_of_birth')}
                error={errors.date_of_birth}
                max={TODAY_ISO}
                disabled={disabled}
            />

            <div className="sm:col-span-2">
                {googleMapsApiKey !== '' ? (
                    <AddressAutocompleteField
                        label="Address"
                        name="address"
                        value={data.address}
                        onChange={(value) => onChange('address', value)}
                        onAddressSelect={onAddressSelect}
                        apiKey={googleMapsApiKey}
                        onBlur={() => onBlur('address')}
                        error={errors.address}
                        disabled={disabled}
                    />
                ) : (
                    <TextField
                        label="Address"
                        name="address"
                        value={data.address}
                        onChange={(value) => onChange('address', value)}
                        onBlur={() => onBlur('address')}
                        error={errors.address}
                        autocomplete="address-line1"
                        disabled={disabled}
                    />
                )}
            </div>

            <TextField
                label="Address line 2"
                name="address_2"
                value={data.address_2}
                onChange={(value) => onChange('address_2', value)}
                onBlur={() => onBlur('address_2')}
                error={errors.address_2}
                autocomplete="address-line2"
                disabled={disabled}
            />

            <TextField
                label="ZIP code"
                name="zip_code"
                value={data.zip_code}
                onChange={(value) => onChange('zip_code', value)}
                onBlur={() => onBlur('zip_code')}
                error={errors.zip_code}
                autocomplete="postal-code"
                disabled={disabled}
            />

            <TextField
                label="City"
                name="city"
                value={data.city}
                onChange={(value) => onChange('city', value)}
                onBlur={() => onBlur('city')}
                error={errors.city}
                autocomplete="address-level2"
                disabled={disabled}
            />

            <TextField
                label="State"
                name="state"
                value={data.state}
                onChange={(value) => onChange('state', value)}
                onBlur={() => onBlur('state')}
                error={errors.state}
                autocomplete="address-level1"
                disabled={disabled}
            />

            <TextField
                label="Country"
                name="country"
                value={data.country}
                onChange={(value) => onChange('country', value)}
                onBlur={() => onBlur('country')}
                error={errors.country}
                autocomplete="country"
                disabled={disabled}
            />

            <SelectField
                label="Gender"
                name="gender"
                value={data.gender}
                onChange={(value) =>
                    onChange(
                        'gender',
                        value === 'male' || value === 'female' || value === 'other' ? value : null,
                    )
                }
                onBlur={() => onBlur('gender')}
                error={errors.gender}
                options={GENDER_OPTIONS}
                placeholder="Not specified"
                disabled={disabled}
            />
        </div>
    );
}
