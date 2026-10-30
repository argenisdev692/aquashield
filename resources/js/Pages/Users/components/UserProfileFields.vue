<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { AddressAutocompleteField, DateField, PhoneField, SelectField, TextField } from '@/Components/Forms';
import type { AddressSelection } from '@/Components/Forms';
import { USERNAME_HINT } from '@/lib/username';
import type { FormErrorMap } from '@/lib/forms';
import type { UserProfileField } from '@/Modules/Users/schemas/userProfile';

/**
 * The identity block shared by Create and Edit.
 *
 * The parent owns the form object and schema; this component owns the field
 * layout and the input semantics. Errors and blur validation are forwarded so
 * one `useZodForm` remains the only owner of form state.
 */

const props = defineProps<{
    errors: FormErrorMap;
    apiKey: string;
}>();

const emit = defineEmits<{
    touch: [field: UserProfileField];
    clear: [field: UserProfileField];
}>();

const firstName = defineModel<string>('firstName', { required: true });
const lastName = defineModel<string>('lastName', { required: true });
const username = defineModel<string>('username', { required: true });
const email = defineModel<string>('email', { required: true });
const phone = defineModel<string>('phone', { required: true });
const dateOfBirth = defineModel<string>('dateOfBirth', { required: true });
const address = defineModel<string>('address', { required: true });
const address2 = defineModel<string>('address2', { required: true });
const zipCode = defineModel<string>('zipCode', { required: true });
const city = defineModel<string>('city', { required: true });
const state = defineModel<string>('state', { required: true });
const country = defineModel<string>('country', { required: true });
const latitude = defineModel<number | null>('latitude', { required: true });
const longitude = defineModel<number | null>('longitude', { required: true });
const gender = defineModel<string | null>('gender', { required: true });

const onAddressSelected = (selection: AddressSelection): void => {
    address.value = selection.address;
    zipCode.value = selection.zip_code;
    city.value = selection.city;
    state.value = selection.state;
    country.value = selection.country;
    latitude.value = selection.latitude;
    longitude.value = selection.longitude;

    // address_2 is deliberately untouched: a manually typed apartment or suite
    // must survive a new suggestion.

    emit('clear', 'address');
    emit('clear', 'zip_code');
    emit('clear', 'city');
    emit('clear', 'state');
    emit('clear', 'country');
    emit('clear', 'latitude');
    emit('clear', 'longitude');
};

const dateValue = computed<string | null>({
    get: () => (dateOfBirth.value === '' ? null : dateOfBirth.value),
    set: (value) => {
        dateOfBirth.value = value ?? '';
    },
});

const yesterday = computed<Date>(() => {
    const date = new Date();
    date.setDate(date.getDate() - 1);

    return date;
});

// Real-time validation with debounce for username, email, phone (DRY, KISS, clean)
const debounceTimer = ref<number | null>(null);

const debouncedValidate = (field: UserProfileField): void => {
    emit('clear', field);
    if (debounceTimer.value !== null) {
        window.clearTimeout(debounceTimer.value);
    }
    debounceTimer.value = window.setTimeout(() => {
        emit('touch', field);
    }, 300);
};

watch(
    () => username.value,
    () => debouncedValidate('username'),
    { deep: true },
);

watch(
    () => email.value,
    () => debouncedValidate('email'),
    { deep: true },
);

watch(
    () => phone.value,
    () => debouncedValidate('phone'),
    { deep: true },
);
</script>

<template>
    <div class="space-y-8">
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                Identity
            </legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <TextField
                    v-model="firstName"
                    name="first_name"
                    label="First name"
                    autocomplete="given-name"
                    required
                    :error="props.errors.first_name"
                    @update:model-value="emit('clear', 'first_name')"
                    @blur="emit('touch', 'first_name')"
                />

                <TextField
                    v-model="lastName"
                    name="last_name"
                    label="Last name"
                    autocomplete="family-name"
                    :error="props.errors.last_name"
                    @update:model-value="emit('clear', 'last_name')"
                    @blur="emit('touch', 'last_name')"
                />

                <TextField
                    v-model="username"
                    name="username"
                    label="Username"
                    autocomplete="username"
                    :hint="USERNAME_HINT"
                    :maxlength="20"
                    :error="props.errors.username"
                    @update:model-value="emit('clear', 'username')"
                    @blur="emit('touch', 'username')"
                />

                <TextField
                    v-model="email"
                    name="email"
                    type="email"
                    label="Email"
                    autocomplete="email"
                    inputmode="email"
                    required
                    :error="props.errors.email"
                    @update:model-value="emit('clear', 'email')"
                    @blur="emit('touch', 'email')"
                />

                <SelectField
                    v-model="gender"
                    name="gender"
                    label="Gender"
                    :options="[
                        { label: 'Male', value: 'male' },
                        { label: 'Female', value: 'female' },
                        { label: 'Other', value: 'other' },
                    ]"
                    :error="props.errors.gender"
                    @update:model-value="emit('clear', 'gender')"
                    @blur="emit('touch', 'gender')"
                />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                Contact
            </legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <PhoneField
                    v-model="phone"
                    name="phone"
                    label="Phone"
                    :error="props.errors.phone"
                    @update:model-value="emit('clear', 'phone')"
                    @blur="emit('touch', 'phone')"
                />

                <DateField
                    v-model="dateValue"
                    name="date_of_birth"
                    label="Date of birth"
                    :max-date="yesterday"
                    :error="props.errors.date_of_birth"
                    @update:model-value="emit('clear', 'date_of_birth')"
                    @blur="emit('touch', 'date_of_birth')"
                />
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                Location
            </legend>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    <AddressAutocompleteField
                        v-model="address"
                        name="address"
                        label="Address"
                        :api-key="props.apiKey"
                        :error="props.errors.address"
                        @select="onAddressSelected"
                        @update:model-value="emit('clear', 'address')"
                        @blur="emit('touch', 'address')"
                    />
                </div>

                <div class="sm:col-span-2">
                    <TextField
                        v-model="address2"
                        name="address_2"
                        label="Apartment, suite, etc."
                        autocomplete="address-line2"
                        :error="props.errors.address_2"
                        @update:model-value="emit('clear', 'address_2')"
                        @blur="emit('touch', 'address_2')"
                    />
                </div>

                <TextField
                    v-model="zipCode"
                    name="zip_code"
                    label="ZIP / postal code"
                    autocomplete="postal-code"
                    :error="props.errors.zip_code"
                    @update:model-value="emit('clear', 'zip_code')"
                    @blur="emit('touch', 'zip_code')"
                />

                <TextField
                    v-model="city"
                    name="city"
                    label="City"
                    autocomplete="address-level2"
                    :error="props.errors.city"
                    @update:model-value="emit('clear', 'city')"
                    @blur="emit('touch', 'city')"
                />

                <TextField
                    v-model="state"
                    name="state"
                    label="State / region"
                    autocomplete="address-level1"
                    :error="props.errors.state"
                    @update:model-value="emit('clear', 'state')"
                    @blur="emit('touch', 'state')"
                />

                <TextField
                    v-model="country"
                    name="country"
                    label="Country"
                    autocomplete="country-name"
                    :error="props.errors.country"
                    @update:model-value="emit('clear', 'country')"
                    @blur="emit('touch', 'country')"
                />
            </div>
        </fieldset>
    </div>
</template>
