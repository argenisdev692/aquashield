<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    useId,
    useTemplateRef,
    watch,
} from 'vue';
import SignaturePad from 'signature_pad';
import { formatBytes, validateFiles } from '@/lib/files';
import { useTheme } from '@/lib/theme';

/**
 * Signature capture: a canvas for pointer/pen input plus a real file input as
 * the keyboard and assistive-technology path. The canvas alone would be an
 * inaccessible control — WCAG 2.2 has no "draw" primitive, so uploading an
 * image is the equivalent action and is always present.
 *
 * The model is a `File` (PNG from the canvas, or the chosen image) so the page
 * can send it to the same upload endpoint as the logos. The stored signature is
 * passed as `currentUrl` and shown until something new is drawn or chosen.
 *
 * Pen colour is read from the canvas's computed `color`, so it follows
 * `text-surface-*` and the `.dark` class instead of freezing a literal.
 */

interface Props {
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /** Signature already on file, shown until a new one is drawn or chosen. */
    currentUrl?: string | null;
}

const {
    label,
    hint = 'Draw with a mouse, pen or finger, or upload a PNG, JPG or WebP image (2 MB maximum).',
    error,
    required = false,
    disabled = false,
    currentUrl = null,
} = defineProps<Props>();

const model = defineModel<File | null>({ default: null });

const uid = useId();
const hintId = `${uid}-hint`;
const errorId = `${uid}-error`;

const canvasEl = useTemplateRef<HTMLCanvasElement>('canvas');
const pad = shallowRef<SignaturePad | null>(null);
const hasInk = ref(false);
const localError = ref<string | null>(null);

const message = computed<string | undefined>(() => error ?? localError.value ?? undefined);

const { isDark } = useTheme();

const penColour = (): string =>
    canvasEl.value === null ? 'currentColor' : getComputedStyle(canvasEl.value).color;

const resize = (): void => {
    const canvas = canvasEl.value;

    if (canvas === null || pad.value === null) {
        return;
    }

    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d')?.scale(ratio, ratio);

    // The browser wipes the bitmap when `width` is assigned. `redraw()` replays
    // the point groups, so a resize does not destroy a half-drawn signature.
    pad.value.redraw();
};

const capture = (): void => {
    const canvas = canvasEl.value;

    if (canvas === null || pad.value === null) {
        return;
    }

    if (pad.value.isEmpty()) {
        hasInk.value = false;
        model.value = null;
        return;
    }

    hasInk.value = true;

    canvas.toBlob((blob) => {
        if (blob !== null) {
            model.value = new File([blob], 'signature.png', { type: 'image/png' });
        }
    }, 'image/png');
};

const clear = (): void => {
    pad.value?.clear();
    hasInk.value = false;
    localError.value = null;
    model.value = null;
};

const onFile = (event: Event): void => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    const file = target.files?.[0] ?? null;
    target.value = '';

    if (file === null) {
        return;
    }

    const invalid = validateFiles([file], {
        accept: 'image/png,image/jpeg,image/webp',
        maxSizeMb: 2,
    });

    if (invalid !== null) {
        localError.value = invalid;
        return;
    }

    localError.value = null;
    pad.value?.clear();
    hasInk.value = false;
    model.value = file;
};

onMounted(() => {
    const canvas = canvasEl.value;

    if (canvas === null) {
        return;
    }

    pad.value = new SignaturePad(canvas, {
        penColor: penColour(),
        minWidth: 0.6,
        maxWidth: 2.4,
    });

    pad.value.addEventListener('end', capture);
    window.addEventListener('resize', resize);
    resize();
});

watch(isDark, () => {
    if (pad.value !== null) {
        pad.value.penColor = penColour();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resize);
    pad.value?.off();
    pad.value = null;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ label }}
            <span v-if="required" class="text-aq-danger-strong" aria-hidden="true">*</span>
            <span v-if="required" class="sr-only">(required)</span>
        </span>

        <img
            v-if="currentUrl !== null && model === null"
            :src="currentUrl"
            alt="Signature currently on file"
            class="h-20 w-auto self-start rounded-md border border-aq-shell-line bg-surface-0 object-contain p-1 dark:bg-surface-900"
        />

        <canvas
            ref="canvas"
            role="img"
            aria-label="Signature drawing area. Use the upload control below if you cannot draw."
            :aria-describedby="message !== undefined ? errorId : hintId"
            class="h-40 w-full touch-none rounded-xl border bg-surface-0 text-surface-900 dark:bg-surface-900 dark:text-surface-0"
            :class="[
                message !== undefined ? 'border-aq-danger/70' : 'border-content-border',
                disabled ? 'pointer-events-none opacity-60' : '',
            ]"
        ></canvas>

        <div class="mt-1 flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border border-content-border px-3 py-1.5 text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-60 motion-reduce:transition-none dark:text-surface-100 dark:hover:bg-surface-800"
                :disabled="disabled || !hasInk"
                @click="clear"
            >
                <i class="pi pi-eraser text-xs" aria-hidden="true" />
                Clear drawing
            </button>

            <label
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-content-border px-3 py-1.5 text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary-500 motion-reduce:transition-none dark:text-surface-100 dark:hover:bg-surface-800"
                :class="disabled ? 'pointer-events-none opacity-60' : ''"
            >
                <i class="pi pi-upload text-xs" aria-hidden="true" />
                Upload image instead
                <input
                    type="file"
                    class="sr-only"
                    accept="image/png,image/jpeg,image/webp"
                    :disabled="disabled"
                    @change="onFile"
                />
            </label>

            <span
                v-if="model !== null"
                class="truncate text-xs text-surface-600 dark:text-surface-300"
            >
                {{ model.name }} · {{ formatBytes(model.size) }}
            </span>
        </div>

        <p
            v-if="hint !== undefined && message === undefined"
            :id="hintId"
            class="text-xs text-surface-600 dark:text-surface-300"
        >
            {{ hint }}
        </p>

        <p
            v-if="message !== undefined"
            :id="errorId"
            role="alert"
            class="text-xs font-medium text-aq-danger-strong"
        >
            {{ message }}
        </p>
    </div>
</template>
