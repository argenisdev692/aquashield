<script setup lang="ts">
/**
 * Command palette — the ⌘/Ctrl-K surface the 2026 SaaS top bar is expected to
 * have. It is deliberately data-driven: the layout owns the command list, so
 * this component never guesses a route or an action.
 *
 * Keyboard model is the ARIA combobox/listbox pair: the input keeps focus the
 * whole time, `aria-activedescendant` tracks the highlighted option, and
 * options are not tabbable. Pointer users get the same rows with
 * `mouseenter` highlighting, which is why the list is not a set of buttons.
 */
import { computed, nextTick, onUnmounted, ref, useId, useTemplateRef, watch } from 'vue';
import type { Command } from '@/lib/commands';

const props = defineProps<{ commands: readonly Command[] }>();

const open = defineModel<boolean>({ default: false });

const query = ref('');
const active = ref(0);
const listId = useId();
const input = useTemplateRef<HTMLInputElement>('input');

/** Focus is restored here on close, not left on a detached node. */
let restoreFocus: HTMLElement | null = null;

const KBD =
    'inline-flex h-5 min-w-5 items-center justify-center rounded border border-surface-200 bg-surface-50 px-1 font-sans text-[10px] font-medium text-surface-600 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300';

const filtered = computed<readonly Command[]>(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return props.commands;
    }

    return props.commands.filter((command) =>
        `${command.label} ${command.keywords ?? ''}`.toLowerCase().includes(needle),
    );
});

/** Grouped for display, with each row keeping its index in the flat list. */
const groups = computed(() => {
    const buckets = new Map<string, { command: Command; index: number }[]>();

    filtered.value.forEach((command, index) => {
        const bucket = buckets.get(command.group) ?? [];
        bucket.push({ command, index });
        buckets.set(command.group, bucket);
    });

    return [...buckets].map(([label, items]) => ({ label, items }));
});

const optionId = (index: number): string => `${listId}-option-${index}`;

const activeOptionId = computed(() =>
    filtered.value.length === 0 ? undefined : optionId(active.value),
);

const close = (): void => {
    open.value = false;
};

const run = (command: Command | undefined): void => {
    if (!command) {
        return;
    }

    close();
    command.run();
};

const move = (step: number): void => {
    const total = filtered.value.length;

    if (total === 0) {
        return;
    }

    active.value = (active.value + step + total) % total;
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
        return;
    }

    if (event.key === 'Home') {
        event.preventDefault();
        active.value = 0;
        return;
    }

    if (event.key === 'End') {
        event.preventDefault();
        active.value = Math.max(filtered.value.length - 1, 0);
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        run(filtered.value[active.value]);
        return;
    }

    if (event.key === 'Tab') {
        // The input is the dialog's only tab stop; without this, Tab escapes
        // to the page behind the modal while it is open.
        event.preventDefault();
        input.value?.focus();
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
};

watch(query, () => {
    active.value = 0;
});

watch(active, async (index) => {
    await nextTick();
    document.getElementById(optionId(index))?.scrollIntoView({ block: 'nearest' });
});

watch(open, async (value) => {
    if (value) {
        restoreFocus =
            document.activeElement instanceof HTMLElement ? document.activeElement : null;
        query.value = '';
        active.value = 0;
        document.body.style.overflow = 'hidden';

        await nextTick();
        input.value?.focus();

        return;
    }

    document.body.style.overflow = '';
    restoreFocus?.focus();
    restoreFocus = null;
});

onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-100 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[80] bg-surface-950/50 backdrop-blur-sm"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
                    leave-to-class="translate-y-1 opacity-0"
                >
                    <div
                        v-if="open"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Command palette"
                        class="mx-auto mt-[12vh] w-[min(40rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-surface-200 bg-surface-0 shadow-2xl dark:border-surface-700 dark:bg-surface-900"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-surface-200 px-4 dark:border-surface-700"
                        >
                            <i class="pi pi-search text-sm text-surface-500" aria-hidden="true" />
                            <input
                                ref="input"
                                v-model="query"
                                type="text"
                                role="combobox"
                                aria-expanded="true"
                                :aria-controls="listId"
                                :aria-activedescendant="activeOptionId"
                                aria-autocomplete="list"
                                aria-label="Search commands"
                                placeholder="Search commands…"
                                autocomplete="off"
                                spellcheck="false"
                                class="h-12 w-full bg-transparent text-sm text-surface-900 outline-none placeholder:text-surface-500 dark:text-surface-0"
                                @keydown="onKeydown"
                            />
                            <kbd :class="KBD">esc</kbd>
                        </div>

                        <div
                            :id="listId"
                            role="listbox"
                            aria-label="Commands"
                            class="max-h-80 overflow-y-auto p-2"
                        >
                            <p
                                v-if="filtered.length === 0"
                                class="px-3 py-8 text-center text-sm text-surface-500 dark:text-surface-400"
                            >
                                No commands match “{{ query }}”.
                            </p>

                            <div v-for="group in groups" :key="group.label">
                                <p
                                    class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-[0.14em] text-surface-500 uppercase dark:text-surface-400"
                                >
                                    {{ group.label }}
                                </p>
                                <div
                                    v-for="{ command, index } in group.items"
                                    :id="optionId(index)"
                                    :key="command.id"
                                    role="option"
                                    :aria-selected="index === active"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors duration-100 motion-reduce:transition-none"
                                    :class="
                                        index === active
                                            ? 'bg-surface-100 text-surface-900 dark:bg-surface-800 dark:text-surface-0'
                                            : 'text-surface-700 dark:text-surface-200'
                                    "
                                    @mouseenter="active = index"
                                    @click="run(command)"
                                >
                                    <i
                                        class="pi text-sm text-aq-accent"
                                        :class="command.icon"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{ command.label }}</span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-4 border-t border-surface-200 px-4 py-2.5 text-[11px] text-surface-500 dark:border-surface-700 dark:text-surface-400"
                        >
                            <span class="flex items-center gap-1">
                                <kbd :class="KBD">↑</kbd>
                                <kbd :class="KBD">↓</kbd>
                                navigate
                            </span>
                            <span class="flex items-center gap-1">
                                <kbd :class="KBD">↵</kbd>
                                select
                            </span>
                            <span class="flex items-center gap-1">
                                <kbd :class="KBD">esc</kbd>
                                close
                            </span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
