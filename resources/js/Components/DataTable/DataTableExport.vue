<script setup lang="ts">
/**
 * Export dropdown — CSV, XLSX and PDF over one contract.
 *
 * The payload is built by the server from the same query string as the table
 * (`useTableState().exportUrl`), so a filtered export covers every matching
 * row, not the fifteen on screen. A client-side spreadsheet/PDF bundle would
 * duplicate the query rules and ship hundreds of kilobytes to do it worse.
 *
 * With `href` omitted the component only emits `export`, which lets a page
 * own a signed URL or a POST flow without changing the kit.
 */
import { computed, ref, useTemplateRef } from 'vue';
import Menu from '@/volt/Menu.vue';
import type { ExportFormat } from './types';
import TableButton from './TableButton.vue';

interface Props {
    formats?: readonly ExportFormat[];
    label?: string;
    disabled?: boolean;
    href?: (format: ExportFormat) => string | undefined;
}

const props = defineProps<Props>();
const emit = defineEmits<{ export: [format: ExportFormat] }>();

const menu = useTemplateRef<{ toggle: (event: Event) => void }>('menu');
const open = ref(false);

interface FormatMeta {
    label: string;
    icon: string;
}

const FORMAT_META: Record<ExportFormat, FormatMeta> = {
    csv: { label: 'CSV', icon: 'pi pi-file-export' },
    xlsx: { label: 'Excel (XLSX)', icon: 'pi pi-file-excel' },
    pdf: { label: 'PDF', icon: 'pi pi-file-pdf' },
};

interface ExportMenuItem {
    label: string;
    icon: string;
    command: () => void;
}

const download = (url: string): void => {
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.rel = 'noopener';
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
};

const run = (format: ExportFormat): void => {
    emit('export', format);

    const url = props.href?.(format);

    if (url) {
        download(url);
    }
};

/** One source of truth for the menu and the disabled state. */
const formats = computed<readonly ExportFormat[]>(() => props.formats ?? ['csv', 'xlsx', 'pdf']);

const items = computed<ExportMenuItem[]>(() =>
    formats.value.map((format) => ({
        label: `Export ${FORMAT_META[format].label}`,
        icon: FORMAT_META[format].icon,
        command: () => run(format),
    })),
);

const toggle = (event: Event): void => {
    menu.value?.toggle(event);
};
</script>

<template>
    <div class="inline-flex">
        <TableButton
            variant="neutral"
            :disabled="props.disabled || formats.length === 0"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="toggle"
        >
            <i class="pi pi-download text-sm" aria-hidden="true" />
            <span>{{ props.label ?? 'Export' }}</span>
            <i
                class="pi pi-chevron-down text-xs text-surface-500 dark:text-surface-400"
                aria-hidden="true"
            />
        </TableButton>

        <Menu ref="menu" :model="items" popup @show="open = true" @hide="open = false" />
    </div>
</template>
