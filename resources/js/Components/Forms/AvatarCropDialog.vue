<script setup lang="ts">
import { ref, useTemplateRef } from 'vue';
import { CircleStencil, Cropper, type CropperResult } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import Dialog from '@/volt/Dialog.vue';
import SecondaryButton from '@/volt/SecondaryButton.vue';
import AppButton from './AppButton.vue';

/**
 * The crop step of `AvatarCropField`, split into its own module.
 *
 * `AvatarCropField` loads this with `defineAsyncComponent`, so the cropper
 * (~34 kB gzip) and its stylesheet are fetched when the user opens the dialog,
 * not on every page that imports the form kit.
 */

interface Props {
    src: string;
    /** Square edge of the produced file. */
    outputSize?: number;
    maxSizeMb?: number;
}

const { src, outputSize = 512, maxSizeMb = 2 } = defineProps<Props>();

const visible = defineModel<boolean>('visible', { required: true });
const emit = defineEmits<{ cropped: [file: File] }>();

interface CropperHandle {
    getResult: () => CropperResult;
    zoom: (factor: number) => void;
    rotate: (angle: number) => void;
    move: (left: number, top?: number) => void;
    reset: () => void;
}

const cropper = useTemplateRef<CropperHandle>('cropper');
const processing = ref(false);
const localError = ref<string | null>(null);

const zoom = (factor: number): void => {
    cropper.value?.zoom(factor);
};

const rotate = (): void => {
    cropper.value?.rotate(90);
};

const move = (left: number, top: number): void => {
    cropper.value?.move(left, top);
};

const reset = (): void => {
    cropper.value?.reset();
};

const close = (): void => {
    visible.value = false;
};

/**
 * `getResult().canvas` is the stencil-sized crop at source resolution. It is
 * redrawn into a square of `outputSize` so the file is deterministic and small,
 * then encoded as WebP (the format the server stores anyway).
 */
const applyCrop = async (): Promise<void> => {
    const result = cropper.value?.getResult();
    const source = result?.canvas;

    if (source === undefined || source === null || source.width === 0) {
        localError.value = 'The image is still loading. Try again in a moment.';
        return;
    }

    processing.value = true;
    localError.value = null;

    try {
        const edge = Math.max(64, Math.min(outputSize, source.width));
        const output = document.createElement('canvas');
        output.width = edge;
        output.height = edge;

        const context = output.getContext('2d');

        if (context === null) {
            throw new Error('This browser cannot crop images.');
        }

        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(source, 0, 0, edge, edge);

        const blob = await new Promise<Blob | null>((resolve) => {
            output.toBlob(resolve, 'image/webp', 0.9);
        });

        if (blob === null) {
            throw new Error('The image could not be encoded. Try another file.');
        }

        // Browsers without WebP encoding return a PNG blob; keep the extension
        // and MIME type consistent with what was actually produced.
        const type = blob.type === 'image/png' ? 'image/png' : 'image/webp';
        const extension = type === 'image/png' ? 'png' : 'webp';
        const file = new File([blob], `avatar-${Date.now()}.${extension}`, { type });

        if (file.size > maxSizeMb * 1024 * 1024) {
            throw new Error(`The cropped image is larger than ${maxSizeMb} MB.`);
        }

        emit('cropped', file);
        visible.value = false;
    } catch (exception) {
        localError.value =
            exception instanceof Error
                ? exception.message
                : 'The image could not be cropped. Try another file.';
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Crop profile photo"
        :draggable="false"
        :resizable="false"
        :dismissable-mask="false"
        :style="{ width: 'min(34rem, 94vw)' }"
    >
        <div class="flex flex-col gap-4">
            <div class="h-80 w-full overflow-hidden rounded-xl bg-surface-100 dark:bg-surface-800">
                <Cropper
                    ref="cropper"
                    :src="src"
                    :stencil-component="CircleStencil"
                    :stencil-props="{ aspectRatio: 1, movable: false, resizable: false }"
                    image-restriction="fill-area"
                    auto-zoom
                    transitions
                    class="h-full w-full"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <SecondaryButton type="button" :disabled="processing" @click="zoom(1.2)">
                    <i class="pi pi-search-plus text-xs" aria-hidden="true" />
                    Zoom in
                </SecondaryButton>

                <SecondaryButton type="button" :disabled="processing" @click="zoom(0.8)">
                    <i class="pi pi-search-minus text-xs" aria-hidden="true" />
                    Zoom out
                </SecondaryButton>

                <SecondaryButton type="button" :disabled="processing" @click="rotate">
                    <i class="pi pi-refresh text-xs" aria-hidden="true" />
                    Rotate
                </SecondaryButton>

                <SecondaryButton type="button" :disabled="processing" @click="reset">
                    <i class="pi pi-replay text-xs" aria-hidden="true" />
                    Reset
                </SecondaryButton>
            </div>

            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Nudge image">
                <span class="me-1 text-xs font-medium text-surface-600 dark:text-surface-300">
                    Nudge
                </span>

                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-md border border-content-border text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                    :disabled="processing"
                    aria-label="Move image up"
                    @click="move(0, -20)"
                >
                    <i class="pi pi-arrow-up text-xs" aria-hidden="true" />
                </button>

                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-md border border-content-border text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                    :disabled="processing"
                    aria-label="Move image down"
                    @click="move(0, 20)"
                >
                    <i class="pi pi-arrow-down text-xs" aria-hidden="true" />
                </button>

                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-md border border-content-border text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                    :disabled="processing"
                    aria-label="Move image left"
                    @click="move(-20, 0)"
                >
                    <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                </button>

                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-md border border-content-border text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                    :disabled="processing"
                    aria-label="Move image right"
                    @click="move(20, 0)"
                >
                    <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
                </button>
            </div>

            <p class="text-xs text-surface-600 dark:text-surface-300">
                Drag the image, or use Nudge, to position the face inside the circle. Zoom to fill
                the frame.
            </p>

            <p v-if="localError" role="alert" class="text-xs font-medium text-aq-danger-strong">
                {{ localError }}
            </p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <SecondaryButton type="button" :disabled="processing" @click="close">
                    Cancel
                </SecondaryButton>

                <AppButton type="button" :loading="processing" @click="applyCrop">
                    Use photo
                </AppButton>
            </div>
        </template>
    </Dialog>
</template>
