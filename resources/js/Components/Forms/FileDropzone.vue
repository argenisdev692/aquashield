<script setup lang="ts">
import { computed } from 'vue';
import DropzoneField from './DropzoneField.vue';
import { formatBytes } from '@/lib/files';

/**
 * Single-file dropzone with a metadata preview (no image thumbnail).
 * Use `ImageDropzone` when the file is an image and the preview matters.
 */

interface Props {
    label: string;
    accept?: string;
    maxSizeMb?: number;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    maxSizeMb: 10,
});

const model = defineModel<File | null>({ default: null });

const onFiles = (files: File[]): void => {
    model.value = files[0] ?? null;
};

const remove = (): void => {
    model.value = null;
};

const icon = computed<string>(() => {
    const type = model.value?.type ?? '';

    if (type === 'application/pdf') {
        return 'pi pi-file-pdf';
    }

    if (type.includes('spreadsheet') || type.includes('excel')) {
        return 'pi pi-file-excel';
    }

    if (type.startsWith('image/')) {
        return 'pi pi-image';
    }

    return 'pi pi-file';
});

const size = computed<string>(() => (model.value ? formatBytes(model.value.size) : ''));
</script>

<template>
    <DropzoneField
        :label="props.label"
        :accept="props.accept"
        :max-size-mb="props.maxSizeMb"
        :hint="props.hint"
        :error="props.error"
        :required="props.required"
        :disabled="props.disabled"
        action-label="Choose file"
        @files="onFiles"
    >
        <div
            v-if="model"
            class="flex items-center gap-3 rounded-lg border border-aq-shell-line bg-surface-50 p-3 dark:bg-surface-800/50"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-lg text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                aria-hidden="true"
            >
                <i :class="icon" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                    {{ model.name }}
                </p>
                <p class="text-xs text-surface-600 dark:text-surface-300">
                    {{ size }}<span v-if="model.type"> · {{ model.type }}</span>
                </p>
            </div>

            <button
                type="button"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-surface-500 transition-colors hover:bg-surface-200 hover:text-surface-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-surface-0 motion-reduce:transition-none"
                aria-label="Remove file"
                @click.prevent.stop="remove"
            >
                <i class="pi pi-times text-sm" aria-hidden="true" />
            </button>
        </div>

        <p v-else class="text-sm text-surface-600 dark:text-surface-300">
            Drop a file here to attach it.
        </p>
    </DropzoneField>
</template>
