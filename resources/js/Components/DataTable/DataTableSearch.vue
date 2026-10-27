<script setup lang="ts">
/**
 * Debounced search, the first control in a server-side table.
 *
 * The input owns a local draft and commits only after the debounce window, so
 * a query key never changes on every keystroke. `onWatcherCleanup` cancels the
 * pending timer when the next keystroke or unmount arrives — the pattern the
 * frontend skill makes mandatory for debounced filters.
 */
import { computed, onBeforeUnmount, onWatcherCleanup, ref, useId, watch } from 'vue';
import TableButton from './TableButton.vue';

interface Props {
    placeholder?: string;
    label?: string;
    minLength?: number;
    debounce?: number;
    loading?: boolean;
}

const props = defineProps<Props>();
const model = defineModel<string>({ default: '' });

const uid = useId();
const hintId = useId();
const draft = ref<string>(model.value);

const minLength = computed<number>(() => props.minLength ?? 2);
const showHint = computed<boolean>(
    () => draft.value.trim().length > 0 && draft.value.trim().length < minLength.value,
);

let timer: ReturnType<typeof setTimeout> | null = null;

const cancelPending = (): void => {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
};

const commit = (): void => {
    const trimmed = draft.value.trim();
    const next = trimmed.length >= (props.minLength ?? 2) ? trimmed : '';

    if (model.value !== next) {
        model.value = next;
    }
};

const commitNow = (): void => {
    cancelPending();
    commit();
};

const clear = (): void => {
    cancelPending();
    draft.value = '';

    if (model.value !== '') {
        model.value = '';
    }
};

watch(draft, () => {
    cancelPending();

    timer = setTimeout(commit, props.debounce ?? 300);

    onWatcherCleanup(cancelPending);
});

/**
 * A page that restores its state from the URL writes the model directly; the
 * draft has to follow or the next keystroke would start from stale text.
 */
watch(model, (value) => {
    if (value !== draft.value) {
        draft.value = value;
    }
});

onBeforeUnmount(cancelPending);
</script>

<template>
    <form
        role="search"
        class="relative w-full"
        :aria-busy="props.loading"
        @submit.prevent="commitNow"
    >
        <label :for="uid" class="sr-only">{{ props.label ?? 'Search' }}</label>

        <i
            class="pi pi-search pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm text-surface-400"
            aria-hidden="true"
        />

        <input
            :id="uid"
            v-model="draft"
            type="text"
            inputmode="search"
            enterkeyhint="search"
            autocomplete="off"
            spellcheck="false"
            :placeholder="props.placeholder ?? 'Search…'"
            :aria-describedby="minLength > 1 ? hintId : undefined"
            class="h-9 w-full rounded-md border border-content-border bg-surface-0 py-2 ps-9 pe-9 text-sm text-surface-700 shadow-sm transition-colors duration-150 placeholder:text-surface-500 hover:border-surface-400 focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:bg-surface-900 dark:text-surface-0 dark:placeholder:text-surface-300 dark:hover:border-surface-600 motion-reduce:transition-none"
        />

        <p
            v-if="minLength > 1"
            :id="hintId"
            :class="[
                'px-1 text-xs text-surface-600 dark:text-surface-300',
                showHint ? '' : 'sr-only',
            ]"
            aria-live="polite"
        >
            Type at least {{ minLength }} characters to search.
        </p>

        <i
            v-if="props.loading"
            class="pi pi-spin pi-spinner absolute end-3 top-1/2 -translate-y-1/2 text-sm text-surface-400"
            aria-hidden="true"
        />

        <TableButton
            v-else-if="draft !== ''"
            variant="ghost"
            size="sm"
            icon-only
            class="absolute end-1 top-1/2 -translate-y-1/2"
            aria-label="Clear search"
            @click="clear"
        >
            <i class="pi pi-times text-xs" aria-hidden="true" />
        </TableButton>
    </form>
</template>
