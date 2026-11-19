<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from 'vue';
import DropzoneField from './DropzoneField.vue';
import { formatBytes } from '@/lib/files';

/**
 * Avatar picker with a crop step.
 *
 * The server already `cover()`s the upload to 320×320, but that crop is decided
 * blindly: a portrait shot can lose the face. This field shows the image in a
 * square/circle cropper first, so the user chooses the framing, and emits the
 * cropped `File` — the raw original never leaves the browser.
 *
 * `vue-advanced-cropper` is the maintained Vue 3 equivalent of
 * `react-easy-crop` (the package is already in `package.json`, 2.8.9, Vue 3
 * branch). There is no official `vue-easy-crop` package.
 *
 * The dialog is a `defineAsyncComponent`: its cropper code and stylesheet are
 * fetched only when the user opens it, not on every page that imports the kit.
 *
 * The model is the cropped `File | null`; `currentUrl` shows the photo already
 * stored on the server until a new one is applied.
 */

interface Props {
    label: string;
    currentUrl?: string | null;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    maxSizeMb?: number;
    /** Square edge of the produced file. 512 is sharp at 2× on a 128px avatar. */
    outputSize?: number;
}

const {
    label,
    currentUrl = null,
    error,
    hint = 'PNG, JPG or WebP, up to 2 MB. You can crop and zoom before saving.',
    required = false,
    disabled = false,
    maxSizeMb = 2,
    outputSize = 512,
} = defineProps<Props>();

const model = defineModel<File | null>({ default: null });

const AvatarCropDialog = defineAsyncComponent(() => import('./AvatarCropDialog.vue'));

const dialogOpen = ref(false);
const draftUrl = ref<string | null>(null);
const previewUrl = ref<string | null>(null);

const displayUrl = computed<string | null>(() => previewUrl.value ?? currentUrl);

const revoke = (url: string | null): void => {
    if (url !== null) {
        URL.revokeObjectURL(url);
    }
};

watch(
    model,
    (file) => {
        revoke(previewUrl.value);
        previewUrl.value = file ? URL.createObjectURL(file) : null;
    },
    { immediate: true },
);

watch(dialogOpen, (open) => {
    if (!open) {
        revoke(draftUrl.value);
        draftUrl.value = null;
    }
});

onBeforeUnmount(() => {
    revoke(draftUrl.value);
    revoke(previewUrl.value);
});

const onFiles = (files: File[]): void => {
    const file = files[0];

    if (file === undefined) {
        return;
    }

    revoke(draftUrl.value);
    draftUrl.value = URL.createObjectURL(file);
    dialogOpen.value = true;
};

const onCropped = (file: File): void => {
    model.value = file;
};

const clearSelection = (): void => {
    model.value = null;
};
</script>

<template>
    <DropzoneField
        :label="label"
        accept="image/png,image/jpeg,image/webp"
        :max-size-mb="maxSizeMb"
        :hint="hint"
        :error="error"
        :required="required"
        :disabled="disabled"
        action-label="Choose image"
        tone="primary"
        @files="onFiles"
    >
        <div class="flex items-center gap-4">
            <img
                v-if="displayUrl"
                :src="displayUrl"
                alt=""
                class="size-20 shrink-0 rounded-full border border-aq-shell-line object-cover"
            />

            <span
                v-else
                class="grid size-20 shrink-0 place-items-center rounded-full bg-aq-accent/15 text-aq-accent"
                aria-hidden="true"
            >
                <i class="pi pi-user text-2xl" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                    {{ model ? model.name : 'No new photo selected' }}
                </p>

                <p class="text-xs text-surface-600 dark:text-surface-300">
                    {{
                        model ? formatBytes(model.size) : 'Choose an image to crop a square avatar.'
                    }}
                </p>

                <button
                    v-if="model && !disabled"
                    type="button"
                    class="mt-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-aq-danger-strong transition-colors hover:bg-aq-danger/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-danger motion-reduce:transition-none"
                    @click.prevent.stop="clearSelection"
                >
                    <i class="pi pi-times text-xs" aria-hidden="true" />
                    Clear selection
                </button>
            </div>
        </div>
    </DropzoneField>

    <AvatarCropDialog
        v-if="dialogOpen && draftUrl"
        v-model:visible="dialogOpen"
        :src="draftUrl"
        :output-size="outputSize"
        :max-size-mb="maxSizeMb"
        @cropped="onCropped"
    />
</template>
