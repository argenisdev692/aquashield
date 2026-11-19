<script setup lang="ts">
/**
 * AddressSection — the US address block on the profile settings screen.
 *
 * The street line is the only autocomplete input. Choosing a Google
 * suggestion fills ZIP, city, state, country and the coordinates, then locks
 * those derived fields so a stray keystroke cannot silently de-sync them from
 * the verified place. Each locked field has a pencil for a deliberate manual
 * override; `address_2` is always manual and is never touched by a selection.
 */
import { computed, nextTick, ref, useTemplateRef } from 'vue';
import { AddressAutocompleteField, TextField } from '@/Components/Forms';
import type { AddressSelection } from '@/Components/Forms';
import type { FormErrorMap } from '@/lib/forms';
import Button from '@/volt/Button.vue';
import Tag from '@/volt/Tag.vue';
import {
    LOCKABLE_ADDRESS_FIELDS,
    type LockableAddressField,
    type ProfileAddressForm,
} from '@/Modules/Users/schemas/profileSettings';
import LockedAddressField from './LockedAddressField.vue';

interface Props {
    apiKey: string;
    /** The page-owned Inertia form proxy; only address fields are written. */
    form: ProfileAddressForm;
    errors: FormErrorMap;
}

const props = defineProps<Props>();

const emit = defineEmits<{ blur: [field: string] }>();

const root = useTemplateRef<HTMLElement>('root');

const unlocked = ref<Set<LockableAddressField>>(new Set());
const autofilled = ref<boolean>(false);

const hasApiKey = computed<boolean>(() => props.apiKey.trim() !== '');
const hasCoordinates = computed<boolean>(
    () => props.form.latitude !== null && props.form.longitude !== null,
);

const street = computed<string>({
    get: () => props.form.address,
    set: (value: string) => {
        if (value === props.form.address) {
            return;
        }

        props.form.address = value;

        /*
         * A manually edited street line no longer matches the stored point.
         * Dropping the coordinates is what turns the derived fields back into
         * editable fields, instead of leaving them locked to a stale place.
         */
        props.form.latitude = null;
        props.form.longitude = null;
        autofilled.value = false;
    },
});

const fieldMeta: Record<
    LockableAddressField,
    { label: string; autocomplete: string; inputmode?: 'numeric' }
> = {
    city: { label: 'City', autocomplete: 'address-level2' },
    state: { label: 'State', autocomplete: 'address-level1' },
    zip_code: { label: 'ZIP code', autocomplete: 'postal-code', inputmode: 'numeric' },
    country: { label: 'Country', autocomplete: 'country-name' },
};

const isLocked = (field: LockableAddressField): boolean =>
    hasCoordinates.value &&
    props.form.address.trim() !== '' &&
    props.form[field].trim() !== '' &&
    !unlocked.value.has(field);

const focusField = async (field: LockableAddressField): Promise<void> => {
    await nextTick();

    root.value?.querySelector<HTMLElement>(`[name="${field}"]`)?.focus();
};

const unlock = (field: LockableAddressField): void => {
    unlocked.value = new Set(unlocked.value).add(field);
    void focusField(field);
};

const lock = (field: LockableAddressField): void => {
    const next = new Set(unlocked.value);
    next.delete(field);
    unlocked.value = next;
};

const lockAll = (): void => {
    unlocked.value = new Set();
};

const onSelect = (selection: AddressSelection): void => {
    props.form.address = selection.address;
    props.form.zip_code = selection.zip_code;
    props.form.city = selection.city;
    props.form.state = selection.state;
    props.form.country = selection.country;
    props.form.latitude = selection.latitude;
    props.form.longitude = selection.longitude;

    // address_2 is deliberately untouched: the apartment, suite or unit line
    // is the user's text and must survive every new address selection.
    unlocked.value = new Set();
    autofilled.value = true;
};

const onAddressInput = (): void => {
    autofilled.value = false;
};
</script>

<template>
    <div ref="root" class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center gap-2">
            <Tag :severity="hasCoordinates ? 'success' : 'secondary'">
                <i
                    class="pi mr-1 text-[0.65rem]"
                    :class="hasCoordinates ? 'pi-check-circle' : 'pi-map-marker'"
                    aria-hidden="true"
                />
                {{ hasCoordinates ? 'Google verified' : 'Manual entry' }}
            </Tag>

            <p class="text-xs text-surface-600 dark:text-surface-300">
                Autocomplete covers US addresses only.
            </p>
        </div>

        <p
            v-if="!hasApiKey"
            role="alert"
            class="rounded-lg border border-aq-warning/40 bg-aq-warning/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
        >
            Add <code class="font-mono text-xs">GOOGLE_MAPS_API_KEY</code> to enable autocomplete.
            You can still enter the address manually.
        </p>

        <AddressAutocompleteField
            v-model="street"
            name="address"
            label="Street address"
            :api-key="props.apiKey"
            :error="props.errors.address"
            hint="Start typing a US address, then choose a suggestion. City, state, ZIP and country fill automatically."
            placeholder="123 Main St"
            @update:model-value="onAddressInput"
            @select="onSelect"
            @blur="emit('blur', 'address')"
        />

        <p
            v-if="autofilled"
            role="status"
            class="flex items-start gap-2 rounded-lg border border-aq-success/30 bg-aq-success/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
        >
            <i class="pi pi-check-circle mt-0.5 shrink-0 text-aq-success" aria-hidden="true" />
            Address details filled from Google. Apartment or suite stays manual in address line 2.
        </p>

        <TextField
            v-model="props.form.address_2"
            name="address_2"
            label="Address line 2"
            autocomplete="address-line2"
            hint="Apartment, suite, unit or building. This field is never overwritten by autocomplete."
            placeholder="Apt 4B"
            :error="props.errors.address_2"
            @blur="emit('blur', 'address_2')"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <template v-for="field in LOCKABLE_ADDRESS_FIELDS" :key="field">
                <LockedAddressField
                    v-if="isLocked(field)"
                    :label="fieldMeta[field].label"
                    :name="field"
                    :value="props.form[field]"
                    :error="props.errors[field]"
                    @unlock="unlock(field)"
                />

                <div v-else class="flex flex-col gap-1.5">
                    <TextField
                        v-model="props.form[field]"
                        :name="field"
                        :label="fieldMeta[field].label"
                        :autocomplete="fieldMeta[field].autocomplete"
                        :inputmode="fieldMeta[field].inputmode"
                        :error="props.errors[field]"
                        @blur="emit('blur', field)"
                    />

                    <Button
                        type="button"
                        text
                        size="small"
                        severity="secondary"
                        class="self-start"
                        @click="lock(field)"
                    >
                        <i class="pi pi-lock mr-1.5 text-xs" aria-hidden="true" />
                        Lock field
                    </Button>
                </div>
            </template>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p v-if="hasCoordinates" class="text-xs text-surface-500 dark:text-surface-400">
                Coordinates:
                <span class="tabular-nums">{{ props.form.latitude?.toFixed(5) }}</span
                >,
                <span class="tabular-nums">{{ props.form.longitude?.toFixed(5) }}</span>
            </p>
            <span v-else class="text-xs text-surface-500 dark:text-surface-400">
                Coordinates are filled when you choose a Google suggestion.
            </span>

            <Button
                v-if="unlocked.size > 0"
                type="button"
                text
                size="small"
                severity="secondary"
                @click="lockAll"
            >
                <i class="pi pi-lock mr-1.5 text-xs" aria-hidden="true" />
                Lock all address fields
            </Button>
        </div>
    </div>
</template>
