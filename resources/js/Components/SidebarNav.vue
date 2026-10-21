<script setup lang="ts">
/**
 * Primary navigation, rendered identically by the desktop rail and the mobile
 * drawer. It owns no open/collapsed state and calls no route: the shell passes
 * the current path in and listens for `navigate`, so the same list cannot drift
 * between the two surfaces.
 *
 * Active state is never colour-only: a 2px accent bar, a tinted row and
 * `aria-current="page"` all fire together, and the row keeps its shape when the
 * rail collapses to icons. Anchor rows are same-page jumps; they emit
 * `navigate` so the drawer can close behind them.
 */
import { Link } from '@inertiajs/vue3';
import type { NavItem, NavGroup } from '@/lib/navigation';
import { isActivePath, jumpToSection, sectionId } from '@/lib/navigation';

const props = defineProps<{
    groups: readonly NavGroup[];
    currentPath: string;
    collapsed?: boolean;
}>();

const emit = defineEmits<{ navigate: [] }>();

const active = (href: string): boolean => isActivePath(props.currentPath, href);

/**
 * The app runs PrimeVue unstyled, so the tooltip directive appends its overlay
 * with no theme classes at all. This string is the tooltip's entire visual
 * definition: a token-based chip that reads the same in both themes.
 *
 * `pointer-events-none` is deliberate — it is a label, not a surface, so it
 * must never swallow a click on the content beside the rail.
 */
const tooltipClass =
    'pointer-events-none absolute rounded-md bg-surface-900 px-2.5 py-1.5 text-xs font-medium whitespace-nowrap text-surface-0 shadow-lg dark:bg-surface-800 dark:ring-1 dark:ring-surface-700';

const onNavigate = (item: NavItem, event: Event): void => {
    if (item.anchor === true) {
        event.preventDefault();
        jumpToSection(sectionId(item.href));
    }

    emit('navigate');
};
</script>

<template>
    <nav aria-label="Primary" class="flex flex-col gap-5">
        <div v-for="group in groups" :key="group.label ?? 'primary'">
            <p
                v-if="group.label"
                class="px-3 pb-1.5 text-[11px] font-semibold tracking-[0.14em] text-surface-500 uppercase dark:text-surface-400"
                :class="collapsed ? 'sr-only' : ''"
            >
                {{ group.label }}
            </p>

            <ul class="flex flex-col gap-0.5">
                <li
                    v-for="item in group.items"
                    :key="item.href"
                    v-tooltip.right="{
                        value: item.label,
                        disabled: collapsed !== true,
                        showDelay: 300,
                        class: tooltipClass,
                    }"
                >
                    <component
                        :is="item.anchor === true ? 'a' : Link"
                        :href="item.href"
                        :aria-current="
                            item.anchor === true
                                ? undefined
                                : active(item.href)
                                  ? 'page'
                                  : undefined
                        "
                        class="group relative flex h-11 items-center gap-3 rounded-lg text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                        :class="[
                            collapsed ? 'justify-center px-0' : 'px-3',
                            active(item.href)
                                ? 'bg-aq-accent/10 text-surface-900 transition-colors duration-150 dark:bg-aq-accent/15 dark:text-surface-0'
                                : 'text-surface-600 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0',
                        ]"
                        @click="onNavigate(item, $event)"
                    >
                        <span
                            v-if="active(item.href)"
                            aria-hidden="true"
                            class="absolute inset-y-2 start-0 w-0.5 rounded-full bg-aq-accent"
                        ></span>

                        <i
                            class="pi shrink-0 text-sm"
                            :class="[
                                item.icon,
                                active(item.href)
                                    ? 'text-aq-accent-deep dark:text-aq-accent'
                                    : 'text-surface-400 dark:text-surface-500',
                            ]"
                            aria-hidden="true"
                        ></i>

                        <span :class="collapsed ? 'sr-only' : 'truncate'">{{ item.label }}</span>
                    </component>
                </li>
            </ul>
        </div>
    </nav>
</template>
