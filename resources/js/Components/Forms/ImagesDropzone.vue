<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DropzoneField from './DropzoneField.vue';
import { formatBytes } from '@/lib/files';

/**
 * Multi-image dropzone with previews and reordering.
 *
 * Reordering is native HTML5 drag plus explicit move buttons. The buttons are
 * the keyboard path — a drag handle alone fails WCAG 2.2 SC 2.5.7 — and both
 * paths announce the new position through a polite live region.
 *
 * `vuedraggable@2` in this repo is Vue 2-only, so this deliberately does not
 * depend on it.
 */

interface Props {
    label: string;
    maxFiles?: number;
    maxSizeMb?: number;
    hint?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    maxFiles: 8,
    maxSizeMb: 5,
});

const model = defineModel<File[]>({ default: () => [] });

interface Preview {
    file: File;
    url: string;
    key: string;
}

const previews = ref<Preview[]>([]);
const urls = new Map<File, { url: string; key: string }>();
let sequence = 0;

const sync = (files: File[]): void => {
    for (const [file, entry] of urls) {
        if (!files.includes(file)) {
            URL.revokeObjectURL(entry.url);
            urls.delete(file);
        }
    }

    previews.value = files.map((file) => {
        let entry = urls.get(file);

        if (!entry) {
            entry = { url: URL.createObjectURL(file), key: `image-${(sequence += 1)}` };
            urls.set(file, entry);
        }

        return { file, ...entry };
    });
};

watch(model, sync, { immediate: true });

onBeforeUnmount(() => {
    for (const entry of urls.values()) {
        URL.revokeObjectURL(entry.url);
    }

    urls.clear();
});

const localError = ref<string | null>(null);
const announcement = ref('');

const displayError = computed<string | undefined>(
    () => props.error ?? localError.value ?? undefined,
);

const announce = (message: string): void => {
    announcement.value = '';
    requestAnimationFrame(() => {
        announcement.value = message;
    });
};

const onFiles = (files: File[]): void => {
    if (model.value.length + files.length > props.maxFiles) {
        localError.value = `You can upload up to ${props.maxFiles} images.`;
        return;
    }

    localError.value = null;
    model.value = [...model.value, ...files];
};

const removeAt = (index: number): void => {
    model.value = model.value.filter((_, position) => position !== index);
    announce(`Removed image ${index + 1}.`);
};

const reorder = (from: number, to: number): void => {
    if (
        from === to ||
        from < 0 ||
        to < 0 ||
        from >= model.value.length ||
        to >= model.value.length
    ) {
        return;
    }

    const next = [...model.value];
    const [item] = next.splice(from, 1);

    if (!item) {
        return;
    }

    next.splice(to, 0, item);
    model.value = next;
    announce(`Moved image ${from + 1} to position ${to + 1}.`);
};

const move = (index: number, delta: number): void => {
    reorder(index, index + delta);
};

const dragIndex = ref<number | null>(null);
const overIndex = ref<number | null>(null);

const onDragStart = (index: number, event: DragEvent): void => {
    dragIndex.value = index;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const onDragOver = (index: number): void => {
    overIndex.value = index;
};

const onDrop = (index: number): void => {
    const from = dragIndex.value;
    dragIndex.value = null;
    overIndex.value = null;

    if (from === null) {
        return;
    }

    reorder(from, index);
};

const onDragEnd = (): void => {
    dragIndex.value = null;
    overIndex.value = null;
};
</script>

<template>
    <DropzoneField
        :label="props.label"
        accept="image/*"
        multiple
        :max-files="props.maxFiles"
        :max-size-mb="props.maxSizeMb"
        :hint="
            props.hint ??
            'First image is the cover. Drag a thumbnail, or use the arrow buttons, to reorder.'
        "
        :error="displayError"
        :required="props.required"
        :disabled="props.disabled"
        action-label="Add images"
        @files="onFiles"
    >
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <div
            v-if="previews.length > 0"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
        >
            <div
                v-for="(item, index) in previews"
                :key="item.key"
                class="group relative cursor-grab overflow-hidden rounded-lg border border-aq-shell-line bg-surface-50 active:cursor-grabbing dark:bg-surface-800/50"
                :class="{
                    'ring-2 ring-primary':
                        overIndex === index && dragIndex !== null && dragIndex !== index,
                }"
                draggable="true"
                title="Drag to reorder"
                @dragstart="onDragStart(index, $event)"
                @dragover.prevent="onDragOver(index)"
                @drop.prevent.stop="onDrop(index)"
                @dragend="onDragEnd"
            >
                <img :src="item.url" alt="" class="aspect-[4/3] w-full object-cover" />

                <span
                    class="absolute start-1 top-1 grid size-6 place-items-center rounded-full bg-surface-900/80 text-xs font-semibold text-surface-0"
                    aria-hidden="true"
                >
                    {{ index + 1 }}
                </span>

                <button
                    type="button"
                    class="absolute end-1 top-1 grid size-7 place-items-center rounded-full bg-surface-900/80 text-surface-0 transition-colors hover:bg-aq-danger focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-danger motion-reduce:transition-none"
                    :aria-label="`Remove image ${index + 1}`"
                    @click.prevent.stop="removeAt(index)"
                >
                    <i class="pi pi-times text-xs" aria-hidden="true" />
                </button>

                <div class="flex items-center justify-between gap-1 p-1.5">
                    <div class="min-w-0">
                        <p
                            class="truncate text-xs font-medium text-surface-800 dark:text-surface-100"
                        >
                            {{ item.file.name }}
                        </p>
                        <p class="text-[11px] text-surface-600 dark:text-surface-300">
                            {{ formatBytes(item.file.size) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center">
                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-md text-surface-600 transition-colors hover:bg-surface-200 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-40 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-surface-0 motion-reduce:transition-none"
                            :disabled="index === 0"
                            :aria-label="`Move image ${index + 1} left`"
                            @click.prevent.stop="move(index, -1)"
                        >
                            <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                        </button>

                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-md text-surface-600 transition-colors hover:bg-surface-200 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-40 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-surface-0 motion-reduce:transition-none"
                            :disabled="index === previews.length - 1"
                            :aria-label="`Move image ${index + 1} right`"
                            @click.prevent.stop="move(index, 1)"
                        >
                            <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="text-sm text-surface-600 dark:text-surface-300">
            Drop images here to preview them.
        </p>
    </DropzoneField>
</template>
