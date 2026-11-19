<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, useTemplateRef, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { onWatcherCleanup } from 'vue';
import Inputtext from '@/volt/Inputtext.vue';
import FieldShell from './FieldShell.vue';
import type { LookupOption } from './types';

/**
 * Async record picker — the CRM lookup, not a static select.
 *
 * A contact list does not fit in a `<select>`: it is paginated, permission
 * filtered and searched server-side. This is the Salesforce "record picker"
 * pattern: the user types, the page's composable searches, and the field stores
 * only the foreign key. The label from the selected row is kept so an edit form
 * can render the current value without a second request; pass `selectedLabel`
 * when the page already has it.
 *
 * The search function is supplied by the caller. This component never calls
 * `fetch` — the network stays in `src/Modules/{ctx}/composables/`, per the
 * frontend boundary. The callback receives an `AbortSignal`, so a new keystroke
 * cancels the previous request instead of racing it.
 */

interface Props {
    label: string;
    name: string;
    /** Runs the server-side search. Rejecting is shown as a recoverable error. */
    search: (query: string, signal: AbortSignal) => Promise<LookupOption[]>;
    /** Label for the value already in the model, for edit forms. */
    selectedLabel?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    emptyMessage?: string;
    minChars?: number;
    debounceMs?: number;
}

const {
    label,
    name,
    search,
    selectedLabel,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder = 'Search…',
    emptyMessage = 'No matches.',
    minChars = 2,
    debounceMs = 250,
} = defineProps<Props>();

const model = defineModel<string | number | null>({ default: null });
const emit = defineEmits<{
    blur: [];
    select: [option: LookupOption];
    clear: [];
}>();

const root = useTemplateRef<HTMLElement>('root');
const listboxId = useId();

const query = ref('');
const options = ref<LookupOption[]>([]);
const selected = ref<LookupOption | null>(null);
const open = ref(false);
const activeIndex = ref(-1);
const loading = ref(false);
const searchError = ref<string | null>(null);

let controller: AbortController | null = null;
let requestId = 0;
let disposed = false;

const isOpen = computed<boolean>(() => open.value && query.value.trim().length >= minChars);

const optionId = (index: number): string => `${listboxId}-option-${index}`;

const activeDescendant = computed<string | undefined>(() =>
    isOpen.value && activeIndex.value >= 0 && options.value.length > 0
        ? optionId(activeIndex.value)
        : undefined,
);

const status = computed<string>(() => {
    if (loading.value) {
        return 'Searching…';
    }

    if (searchError.value !== null) {
        return searchError.value;
    }

    if (!isOpen.value) {
        return '';
    }

    if (options.value.length > 0) {
        return `${options.value.length} results available.`;
    }

    return 'No results.';
});

watch(
    () => [model.value, selectedLabel] as const,
    ([value, nextLabel]) => {
        if (value === null || value === '') {
            selected.value = null;
            return;
        }

        if (selected.value?.value === value) {
            return;
        }

        selected.value =
            nextLabel !== undefined && nextLabel !== '' ? { value, label: nextLabel } : null;
    },
    { immediate: true },
);

const runSearch = async (value: string): Promise<void> => {
    controller?.abort();

    const next = new AbortController();
    controller = next;

    const current = (requestId += 1);
    loading.value = true;
    searchError.value = null;
    open.value = true;

    try {
        const results = await search(value, next.signal);

        if (current !== requestId || disposed) {
            return;
        }

        options.value = results;
        activeIndex.value = results.length > 0 ? 0 : -1;
    } catch {
        if (current !== requestId || next.signal.aborted || disposed) {
            return;
        }

        options.value = [];
        activeIndex.value = -1;
        searchError.value = 'Search is unavailable. Please try again.';
    } finally {
        if (current === requestId) {
            loading.value = false;
        }
    }
};

watch(query, (value) => {
    controller?.abort();
    controller = null;

    const text = value.trim();

    if (text.length < minChars) {
        options.value = [];
        open.value = false;
        activeIndex.value = -1;
        return;
    }

    const timer = window.setTimeout(
        () => {
            void runSearch(text);
        },
        Math.max(0, debounceMs),
    );

    onWatcherCleanup(() => window.clearTimeout(timer));
});

const close = (): void => {
    open.value = false;
    activeIndex.value = -1;
};

const choose = (index: number): void => {
    const option = options.value[index];

    if (option === undefined) {
        return;
    }

    model.value = option.value;
    selected.value = option;
    query.value = '';
    options.value = [];
    close();
    emit('select', option);
};

const clearSelection = (): void => {
    model.value = null;
    selected.value = null;
    query.value = '';
    options.value = [];
    close();
    emit('clear');
};

const onFocus = (): void => {
    if (
        query.value.trim().length >= minChars &&
        (options.value.length > 0 || searchError.value !== null)
    ) {
        open.value = true;
        activeIndex.value = options.value.length > 0 ? 0 : -1;
    }
};

const onBlur = (): void => {
    emit('blur');
    close();
};

const onKeydown = (event: KeyboardEvent): void => {
    if (!isOpen.value) {
        if (event.key === 'ArrowDown' && options.value.length > 0) {
            event.preventDefault();
            open.value = true;
            activeIndex.value = 0;
        }

        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            activeIndex.value =
                options.value.length === 0 ? -1 : (activeIndex.value + 1) % options.value.length;
            break;
        case 'ArrowUp':
            event.preventDefault();
            activeIndex.value =
                options.value.length === 0
                    ? -1
                    : (activeIndex.value - 1 + options.value.length) % options.value.length;
            break;
        case 'Enter':
            if (activeIndex.value >= 0) {
                event.preventDefault();
                choose(activeIndex.value);
            }
            break;
        case 'Escape':
            event.preventDefault();
            close();
            break;
        case 'Tab':
            close();
            break;
    }
};

onClickOutside(root, close);

onBeforeUnmount(() => {
    disposed = true;
    requestId += 1;
    controller?.abort();
});
</script>

<template>
    <FieldShell
        v-slot="{ id, describedBy }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
        :required="required"
    >
        <div ref="root" class="relative flex flex-col gap-2">
            <div
                v-if="selected !== null"
                class="flex items-start justify-between gap-2 rounded-md border border-surface-300 bg-surface-50 px-3 py-2 dark:border-surface-700 dark:bg-surface-800"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                        {{ selected.label }}
                    </p>

                    <p
                        v-if="selected.description"
                        class="truncate text-xs text-surface-600 dark:text-surface-300"
                    >
                        {{ selected.description }}
                    </p>
                </div>

                <button
                    v-if="!disabled"
                    type="button"
                    class="grid size-6 shrink-0 place-items-center rounded-md text-surface-500 transition-colors hover:bg-surface-200 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 dark:text-surface-400 dark:hover:bg-surface-700 dark:hover:text-surface-0 motion-reduce:transition-none"
                    :aria-label="`Clear ${label}`"
                    @click="clearSelection"
                >
                    <i class="pi pi-times text-xs" aria-hidden="true" />
                </button>
            </div>

            <Inputtext
                :id="id"
                v-model="query"
                :name="name"
                type="text"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                :placeholder="selected !== null ? 'Search to replace…' : placeholder"
                :disabled="disabled"
                :aria-describedby="describedBy"
                :aria-invalid="error ? true : undefined"
                :aria-expanded="isOpen"
                :aria-busy="loading || undefined"
                :aria-controls="isOpen ? listboxId : undefined"
                :aria-activedescendant="activeDescendant"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                role="combobox"
                :invalid="Boolean(error)"
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
                :aria-label="`${label} results`"
            >
                <li
                    v-if="loading"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-surface-600 dark:text-surface-300"
                    role="presentation"
                >
                    <i
                        class="pi pi-spinner animate-spin text-xs motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    Searching…
                </li>

                <li
                    v-else-if="searchError !== null"
                    class="px-3 py-2 text-sm text-aq-danger-strong"
                    role="presentation"
                >
                    {{ searchError }}
                </li>

                <li
                    v-else-if="options.length === 0"
                    class="px-3 py-2 text-sm text-surface-600 dark:text-surface-300"
                    role="presentation"
                >
                    {{ emptyMessage }}
                </li>

                <template v-else>
                    <template v-for="(option, index) in options" :key="String(option.value)">
                        <li
                            v-if="
                                option.group !== undefined &&
                                option.group !== options[index - 1]?.group
                            "
                            role="presentation"
                            class="px-3 pb-1 pt-2 text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            {{ option.group }}
                        </li>

                        <li
                            :id="optionId(index)"
                            role="option"
                            :aria-selected="index === activeIndex"
                            class="cursor-pointer px-3 py-2 text-surface-800 dark:text-surface-100"
                            :class="
                                index === activeIndex
                                    ? 'bg-primary-50 dark:bg-primary-950'
                                    : 'hover:bg-surface-100 dark:hover:bg-surface-800'
                            "
                            @mouseenter="activeIndex = index"
                            @mousedown.prevent="choose(index)"
                        >
                            <span class="block text-sm">{{ option.label }}</span>

                            <span
                                v-if="option.description"
                                class="block text-xs text-surface-600 dark:text-surface-300"
                            >
                                {{ option.description }}
                            </span>
                        </li>
                    </template>
                </template>
            </ul>
        </div>
    </FieldShell>
</template>
