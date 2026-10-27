<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import DropzoneField from './DropzoneField.vue';
import { formatBytes } from '@/lib/files';

/**
 * Single-image dropzone with an object-URL preview.
 * The URL is revoked whenever the model changes or the component unmounts;
 * a leak here is a memory leak per upload, not a cosmetic bug.
 */

interface Props {
    label: string;
    maxSizeMb?: number;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    maxSizeMb: 5,
});

const model = defineModel<File | null>({ default: null });

const previewUrl = ref<string | null>(null);

const sync = (file: File | null): void => {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = file ? URL.createObjectURL(file) : null;
};

watch(model, sync, { immediate: true });

onBeforeUnmount(() => {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
});

const onFiles = (files: File[]): void => {
    model.value = files[0] ?? null;
};

const remove = (): void => {
    model.value = null;
};
</script>

<template>
    <DropzoneField
        :label="props.label"
        accept="image/*"
        :max-size-mb="props.maxSizeMb"
        :hint="props.hint ?? 'PNG, JPG, WebP or AVIF.'"
        :error="props.error"
        :required="props.required"
        :disabled="props.disabled"
        action-label="Choose image"
        @files="onFiles"
    >
        <div v-if="model && previewUrl" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <img
                :src="previewUrl"
                alt=""
                class="h-32 w-full rounded-lg border border-aq-shell-line object-cover sm:w-48"
            />

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                    {{ model.name }}
                </p>
                <p class="text-xs text-surface-600 dark:text-surface-300">
                    {{ formatBytes(model.size) }}
                </p>

                <button
                    type="button"
                    class="mt-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-aq-danger-strong transition-colors hover:bg-aq-danger/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-danger motion-reduce:transition-none"
                    @click.prevent.stop="remove"
                >
                    <i class="pi pi-trash text-xs" aria-hidden="true" />
                    Remove image
                </button>
            </div>
        </div>

        <p v-else class="text-sm text-surface-600 dark:text-surface-300">
            Drop an image here to preview it.
        </p>
    </DropzoneField>
</template>
