<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { validateFiles } from '@/lib/files';

/**
 * The shared drop surface behind the three upload fields.
 *
 * Native `<input type="file">` is the control; the styled area is only a
 * label. That keeps the picker keyboard-reachable, gives mobile the OS sheet,
 * and avoids a JS-only click target. Drag state is visual only — the drop
 * handler is the contract.
 */

interface Props {
    label: string;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    maxSizeMb?: number;
    disabled?: boolean;
    hint?: string;
    error?: string;
    required?: boolean;
    actionLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    maxSizeMb: 10,
    actionLabel: 'Choose file',
});

const emit = defineEmits<{
    files: [files: File[]];
    reject: [message: string];
}>();

defineSlots<{ default(props: { dragging: boolean }): unknown }>();

const inputId = useId();
const hintId = useId();
const errorId = useId();

const dragging = ref(false);
const localError = ref<string | null>(null);

const message = computed<string | undefined>(() => props.error ?? localError.value ?? undefined);

const describedBy = computed<string | undefined>(() => {
    const ids = [props.hint ? hintId : undefined, message.value ? errorId : undefined].filter(
        (value): value is string => value !== undefined,
    );

    return ids.length > 0 ? ids.join(' ') : undefined;
});

const acceptFiles = (files: File[]): void => {
    const selected = props.multiple ? files : files.slice(0, 1);
    const error = validateFiles(selected, {
        accept: props.accept,
        maxSizeMb: props.maxSizeMb,
        maxFiles: props.multiple ? props.maxFiles : 1,
    });

    if (error !== null) {
        localError.value = error;
        emit('reject', error);
        return;
    }

    localError.value = null;
    emit('files', selected);
};

const onChange = (event: Event): void => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    acceptFiles(Array.from(target.files ?? []));
    target.value = '';
};

const onDrop = (event: DragEvent): void => {
    dragging.value = false;
    acceptFiles(Array.from(event.dataTransfer?.files ?? []));
};
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ props.label }}
            <span v-if="props.required" class="text-aq-danger-strong" aria-hidden="true">*</span>
            <span v-if="props.required" class="sr-only">(required)</span>
        </span>

        <div
            class="rounded-xl border border-dashed p-3 transition-colors duration-150 motion-reduce:transition-none"
            :class="[
                dragging
                    ? 'border-primary bg-primary-50/60 dark:bg-primary-950/30'
                    : 'border-content-border',
                message ? 'border-aq-danger/70' : '',
                props.disabled ? 'opacity-60' : '',
            ]"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <slot :dragging="dragging" />

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <label
                    :for="inputId"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-content-border bg-surface-0 px-3 py-1.5 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary-500 dark:bg-surface-900 dark:text-surface-100 dark:hover:bg-surface-800 motion-reduce:transition-none"
                >
                    {{ props.actionLabel }}
                </label>
                <span class="text-xs text-surface-600 dark:text-surface-300">
                    or drag and drop
                </span>
            </div>

            <input
                :id="inputId"
                class="sr-only"
                type="file"
                :accept="props.accept"
                :multiple="props.multiple"
                :disabled="props.disabled"
                :aria-label="props.label"
                :aria-describedby="describedBy"
                :aria-invalid="message ? true : undefined"
                @change="onChange"
            />
        </div>

        <p
            v-if="props.hint && !message"
            :id="hintId"
            class="text-xs text-surface-600 dark:text-surface-300"
        >
            {{ props.hint }}
        </p>

        <p
            v-if="message"
            :id="errorId"
            role="alert"
            class="text-xs font-medium text-aq-danger-strong"
        >
            {{ message }}
        </p>
    </div>
</template>
