<script setup lang="ts">
/**
 * Header inbox dropdown — the bell and envelope surfaces.
 *
 * Both instances render the same shell, so the header has one predictable
 * panel pattern instead of two bespoke overlays. The list is data-driven and
 * defaults to empty: this app has no notifications or messages table yet, so
 * the panel shows an honest empty state rather than a fabricated unread count.
 * When a backend starts passing `items`, the badge and rows appear with no
 * layout change.
 */
import { computed, ref, useId, useTemplateRef } from 'vue';
import { Link } from '@inertiajs/vue3';
import Popover from '@/volt/Popover.vue';

interface InboxItem {
    id: string;
    title: string;
    body?: string;
    /** Pre-formatted relative time, e.g. "2 min ago". */
    time: string;
    /** In-app destination; omit for a non-navigable entry. */
    href?: string;
    unread?: boolean;
}

const props = withDefaults(
    defineProps<{
        kind: 'notifications' | 'messages';
        items?: readonly InboxItem[];
    }>(),
    { items: () => [] },
);

const open = ref(false);
const panelId = useId();
const popover = useTemplateRef<{ toggle: (event: Event) => void; hide: () => void }>('popover');

const copy = computed(() =>
    props.kind === 'notifications'
        ? {
              label: 'Notifications',
              icon: 'pi-bell',
              emptyTitle: "You're all caught up",
              emptyBody: 'Pipeline alerts, mentions and task reminders will land here.',
          }
        : {
              label: 'Messages',
              icon: 'pi-envelope',
              emptyTitle: 'No messages yet',
              emptyBody: 'Client replies and teammate threads will land here.',
          },
);

const unread = computed(() => props.items.filter((item) => item.unread).length);
const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));

const toggle = (event: MouseEvent): void => {
    popover.value?.toggle(event);
};

const hide = (): void => {
    popover.value?.hide();
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative grid size-9 place-items-center rounded-lg text-surface-600 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
            :aria-label="unread > 0 ? `${copy.label}, ${unread} unread` : copy.label"
            :aria-controls="panelId"
            aria-haspopup="dialog"
            :aria-expanded="open"
            @click="toggle"
            @keydown.esc="hide"
        >
            <i class="pi text-base" :class="copy.icon" aria-hidden="true" />
            <span
                v-if="unread > 0"
                class="absolute top-1 right-1 grid size-4 place-items-center rounded-full bg-aq-accent text-[10px] font-semibold text-aq-void"
                aria-hidden="true"
            >
                {{ badge }}
            </span>
        </button>

        <Popover
            ref="popover"
            class="before:hidden after:hidden"
            :aria-label="copy.label"
            @show="open = true"
            @hide="open = false"
        >
            <div :id="panelId" class="w-[min(20rem,calc(100vw-2rem))]">
                <div class="flex items-center justify-between gap-3 px-1 pb-2">
                    <p class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        {{ copy.label }}
                    </p>
                    <span
                        v-if="items.length > 0"
                        class="text-xs text-surface-500 dark:text-surface-400"
                    >
                        {{ items.length }}
                    </span>
                </div>

                <ul v-if="items.length > 0" class="space-y-1">
                    <li v-for="item in items" :key="item.id">
                        <component
                            :is="item.href ? Link : 'div'"
                            v-bind="item.href ? { href: item.href } : {}"
                            class="flex gap-3 rounded-lg px-2 py-2 transition-colors duration-150 hover:bg-surface-100 motion-reduce:transition-none dark:hover:bg-surface-800"
                            @click="item.href ? hide() : undefined"
                        >
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full"
                                :class="
                                    item.unread
                                        ? 'bg-aq-accent'
                                        : 'bg-surface-300 dark:bg-surface-600'
                                "
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-medium text-surface-900 dark:text-surface-0"
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    v-if="item.body"
                                    class="mt-0.5 block text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                                >
                                    {{ item.body }}
                                </span>
                                <span
                                    class="mt-1 block text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    {{ item.time }}
                                </span>
                            </span>
                        </component>
                    </li>
                </ul>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-surface-300 px-4 py-6 text-center dark:border-surface-700"
                >
                    <span
                        class="mx-auto grid size-9 place-items-center rounded-full bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400"
                    >
                        <i class="pi text-sm" :class="copy.icon" aria-hidden="true" />
                    </span>
                    <p class="mt-3 text-sm font-medium text-surface-900 dark:text-surface-0">
                        {{ copy.emptyTitle }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                        {{ copy.emptyBody }}
                    </p>
                </div>
            </div>
        </Popover>
    </div>
</template>
