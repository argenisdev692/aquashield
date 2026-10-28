<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, useTemplateRef, watch } from 'vue';
import { onWatcherCleanup } from 'vue';
import { onClickOutside } from '@vueuse/core';
import Inputtext from '@/volt/Inputtext.vue';
import { loadPlacesLibrary } from '@/lib/google-maps';
import FieldShell from './FieldShell.vue';
import type { AddressSelection } from './types';

/**
 * Google Place Autocomplete for the form kit.
 *
 * It uses the Places API (New) through `AutocompleteSuggestion`, not the
 * legacy `google.maps.places.Autocomplete` widget: Google stopped offering
 * the legacy widget to new customers on 2025-03-01 and recommends this path.
 * The input, listbox and keyboard model are ours, so the field has the same
 * label/error/ARIA contract as every other control in the kit.
 *
 * The component only emits the canonical address projection. The page owns
 * the form and therefore decides which fields to update; `address_2` is never
 * part of the emitted selection, which keeps a manually typed apartment or
 * suite intact.
 */

interface Props {
    label: string;
    name: string;
    apiKey: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    /** Optional CLDR two-letter region restriction, e.g. `['US']`. */
    includedRegionCodes?: string[];
}

const props = withDefaults(defineProps<Props>(), {
    includedRegionCodes: () => [],
});

const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ blur: []; select: [selection: AddressSelection] }>();

const root = useTemplateRef<HTMLElement>('root');
const listboxId = useId();

const suggestions = ref<google.maps.places.PlacePrediction[]>([]);
const open = ref(false);
const activeIndex = ref(-1);
const loading = ref(false);
const errorMessage = ref<string | null>(null);
const loadingError = ref<string | null>(null);
const loaded = ref(false);

let sessionToken: google.maps.places.AutocompleteSessionToken | null = null;
let requestId = 0;
let suppressWatch = false;
let disposed = false;

const hasApiKey = computed<boolean>(() => props.apiKey.trim() !== '');

const resolvedHint = computed<string>(() => {
    if (!hasApiKey.value) {
        return 'Address autocomplete is not configured. Enter the address manually.';
    }

    return props.hint ?? 'Start typing and pick a suggestion to fill the address fields.';
});

const failureMessage = computed<string | null>(() => loadingError.value ?? errorMessage.value);
const isOpen = computed<boolean>(() => open.value && suggestions.value.length > 0);

const optionId = (index: number): string => `${listboxId}-option-${index}`;

const activeDescendant = computed<string | undefined>(() =>
    isOpen.value && activeIndex.value >= 0 ? optionId(activeIndex.value) : undefined,
);

const status = computed<string>(() => {
    if (loading.value) {
        return 'Searching addresses…';
    }

    if (isOpen.value) {
        return `${suggestions.value.length} address suggestions available.`;
    }

    if (open.value && model.value.trim().length >= 2) {
        return 'No addresses found.';
    }

    return '';
});

const closeList = (): void => {
    open.value = false;
    activeIndex.value = -1;
    suggestions.value = [];
};

const invalidate = (): void => {
    requestId += 1;
};

const closeAndInvalidate = (): void => {
    invalidate();
    closeList();
};

const ensureLoaded = async (): Promise<boolean> => {
    if (!hasApiKey.value) {
        return false;
    }

    if (loaded.value) {
        return true;
    }

    try {
        await loadPlacesLibrary(props.apiKey);
        loaded.value = true;

        return true;
    } catch {
        loadingError.value = 'Address search is unavailable. Enter the address manually.';

        return false;
    }
};

const toSelection = (place: google.maps.places.Place): AddressSelection => {
    const component = (type: string): google.maps.places.AddressComponent | undefined =>
        place.addressComponents?.find((candidate) => candidate.types.includes(type));

    const longText = (type: string): string => component(type)?.longText ?? '';
    const shortText = (type: string): string => component(type)?.shortText ?? '';

    const streetNumber = longText('street_number');
    const route = longText('route');
    const streetAddress =
        longText('street_address') || [streetNumber, route].filter((part) => part !== '').join(' ');

    const postalCode = longText('postal_code');
    const postalSuffix = longText('postal_code_suffix');
    const city =
        longText('locality') ||
        longText('postal_town') ||
        longText('sublocality_level_1') ||
        longText('administrative_area_level_2');
    const state = shortText('administrative_area_level_1');
    const country = longText('country');
    const location = place.location;

    return {
        address: streetAddress || (place.formattedAddress ?? ''),
        formatted_address: place.formattedAddress ?? '',
        zip_code: postalSuffix === '' ? postalCode : `${postalCode}-${postalSuffix}`,
        city,
        state,
        country,
        latitude: location?.lat() ?? null,
        longitude: location?.lng() ?? null,
    };
};

const fetchSuggestions = async (query: string): Promise<void> => {
    if (!(await ensureLoaded())) {
        return;
    }

    const current = ++requestId;
    loading.value = true;
    errorMessage.value = null;

    try {
        const token = sessionToken ?? new google.maps.places.AutocompleteSessionToken();
        sessionToken = token;

        const request: google.maps.places.AutocompleteRequest = {
            input: query,
            sessionToken: token,
        };

        if (props.includedRegionCodes.length > 0) {
            request.includedRegionCodes = props.includedRegionCodes;
        }

        const { suggestions: next } =
            await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions(request);

        if (current !== requestId || disposed) {
            return;
        }

        suggestions.value = next
            .map((suggestion) => suggestion.placePrediction)
            .filter(
                (prediction): prediction is google.maps.places.PlacePrediction =>
                    prediction !== null,
            );
        open.value = suggestions.value.length > 0;
        activeIndex.value = -1;
    } catch {
        if (current !== requestId || disposed) {
            return;
        }

        errorMessage.value = 'Address search is unavailable. Enter the address manually.';
        closeList();
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
};

const chooseSuggestion = async (index: number): Promise<void> => {
    const prediction = suggestions.value[index];

    if (prediction === undefined) {
        return;
    }

    const current = requestId;

    suppressWatch = true;
    closeList();

    try {
        const place = prediction.toPlace();
        await place.fetchFields({
            fields: ['addressComponents', 'formattedAddress', 'location'],
        });

        if (current !== requestId || disposed) {
            return;
        }

        const selection = toSelection(place);

        model.value = selection.address;
        sessionToken = null;
        emit('select', selection);
    } catch {
        if (current === requestId) {
            errorMessage.value = 'That address could not be loaded. Enter it manually.';
        }
    } finally {
        void nextTick(() => {
            suppressWatch = false;
        });
    }
};

const onFocus = (): void => {
    if (suggestions.value.length > 0) {
        open.value = true;
    }

    void ensureLoaded();
};

const onBlur = (): void => {
    emit('blur');
    closeAndInvalidate();
};

const onKeydown = (event: KeyboardEvent): void => {
    if (!isOpen.value) {
        if (event.key === 'ArrowDown' && suggestions.value.length > 0) {
            event.preventDefault();
            open.value = true;
        }

        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
            break;
        case 'ArrowUp':
            event.preventDefault();
            activeIndex.value =
                (activeIndex.value - 1 + suggestions.value.length) % suggestions.value.length;
            break;
        case 'Enter':
            if (activeIndex.value >= 0) {
                event.preventDefault();
                void chooseSuggestion(activeIndex.value);
            }
            break;
        case 'Escape':
            event.preventDefault();
            closeAndInvalidate();
            break;
        case 'Tab':
            closeList();
            break;
    }
};

onClickOutside(root, closeAndInvalidate);

watch(model, (value) => {
    if (suppressWatch) {
        return;
    }

    const query = value.trim();
    invalidate();

    if (query.length < 2) {
        closeList();
        return;
    }

    const timer = window.setTimeout(() => {
        void fetchSuggestions(query);
    }, 180);

    onWatcherCleanup(() => window.clearTimeout(timer));
});

onBeforeUnmount(() => {
    disposed = true;
    invalidate();
});
</script>

<template>
    <FieldShell
        v-slot="{ id, describedBy }"
        :label="props.label"
        :name="props.name"
        :error="props.error ?? failureMessage ?? undefined"
        :hint="resolvedHint"
        :required="props.required"
    >
        <div ref="root" class="relative">
            <Inputtext
                :id="id"
                v-model="model"
                :name="props.name"
                type="text"
                autocomplete="off"
                autocapitalize="off"
                :placeholder="props.placeholder ?? 'Start typing an address…'"
                :disabled="props.disabled"
                :aria-describedby="describedBy"
                :aria-invalid="props.error || failureMessage ? true : undefined"
                :aria-expanded="isOpen"
                :aria-controls="listboxId"
                :aria-activedescendant="activeDescendant"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                role="combobox"
                :invalid="Boolean(props.error || failureMessage)"
                spellcheck="false"
                fluid
                @focus="onFocus"
                @blur="onBlur"
                @keydown="onKeydown"
            />

            <p class="sr-only" aria-live="polite">{{ status }}</p>

            <ul
                v-if="isOpen"
                :id="listboxId"
                class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-auto rounded-md border border-surface-300 bg-surface-0 py-1 shadow-lg dark:border-surface-700 dark:bg-surface-900"
                role="listbox"
            >
                <li
                    v-for="(prediction, index) in suggestions"
                    :id="optionId(index)"
                    :key="prediction.placeId"
                    role="option"
                    :aria-selected="index === activeIndex"
                    class="cursor-pointer px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                    :class="
                        index === activeIndex
                            ? 'bg-primary-50 dark:bg-primary-950'
                            : 'hover:bg-surface-100 dark:hover:bg-surface-800'
                    "
                    @mouseenter="activeIndex = index"
                    @mousedown.prevent="chooseSuggestion(index)"
                >
                    {{ prediction.text.toString() }}
                </li>
            </ul>

            <p class="mt-1 text-[11px] text-surface-500 dark:text-surface-400">
                <a
                    href="https://maps.google.com/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline underline-offset-2 transition-colors duration-150 hover:text-surface-700 dark:hover:text-surface-200 motion-reduce:transition-none"
                >
                    Powered by Google
                </a>
            </p>
        </div>
    </FieldShell>
</template>
