<script setup lang="ts">
/**
 * App navigation shell — a persistent rail from `lg` up, a modal drawer below
 * it. Both render `SidebarNav`, so the destination list and active state are
 * one implementation.
 *
 * The rail is a plain `<aside>`, never a Drawer: PrimeVue's Drawer is a modal
 * overlay with a mask and scroll lock, which is the wrong contract for chrome
 * that has to stay on screen. The drawer is only for the breakpoint where a
 * 256px column would eat the content.
 *
 * Collapse is user-controlled and persisted (`aq:nav:collapsed`), per the 2026
 * sidebar pattern: a rail someone collapsed must stay collapsed on the next
 * visit. Width is the only thing that animates, and it is CSS-only.
 */
import { useMediaQuery, useStorage } from '@vueuse/core';
import { computed, useId, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidebarNav from '@/Components/SidebarNav.vue';
import {
    DASHBOARD_PATH,
    DASHBOARD_SECTIONS,
    visiblePrimaryNav,
    type NavGroup,
} from '@/lib/navigation';
import Drawer from '@/volt/Drawer.vue';

const props = defineProps<{
    currentPath: string;
    /** Stable id the header trigger points at with `aria-controls`. */
    panelId: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const collapsed = useStorage<boolean>('aq:nav:collapsed', false);
const isDesktop = useMediaQuery('(min-width: 64rem)');
const railId = useId();

const page = usePage<{ authPermissions?: string[] }>();
const primaryNav = computed(() => visiblePrimaryNav(page.props.authPermissions ?? []));

/**
 * Section jumps only exist while the dashboard is mounted. Showing them on the
 * auth screens would be a link to nowhere, so the group is conditional rather
 * than disabled-looking.
 */
const groups = computed<readonly NavGroup[]>(() => {
    const result: NavGroup[] = [{ items: primaryNav.value }];

    if (props.currentPath === DASHBOARD_PATH) {
        result.push({ label: 'On this page', items: DASHBOARD_SECTIONS });
    }

    return result;
});

/** A phone drawer left open while the viewport grows would mask the desktop rail. */
watch(isDesktop, (desktop) => {
    if (desktop) {
        open.value = false;
    }
});
</script>

<template>
    <aside
        :id="railId"
        class="sticky top-16 hidden h-[calc(100dvh-4rem)] shrink-0 flex-col border-e border-aq-shell-line bg-surface-0/40 backdrop-blur-sm transition-[width] duration-200 ease-out motion-reduce:transition-none lg:flex dark:bg-surface-950/40"
        :class="collapsed ? 'w-18' : 'w-64'"
    >
        <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <SidebarNav :groups="groups" :current-path="currentPath" :collapsed="collapsed" />
        </div>

        <div class="border-t border-aq-shell-line p-2">
            <button
                type="button"
                class="flex h-10 w-full items-center gap-3 rounded-lg text-sm font-medium text-surface-600 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
                :class="collapsed ? 'justify-center px-0' : 'px-3'"
                :aria-expanded="!collapsed"
                :aria-controls="railId"
                @click="collapsed = !collapsed"
            >
                <i
                    class="pi shrink-0 text-sm"
                    :class="collapsed ? 'pi-angle-double-right' : 'pi-angle-double-left'"
                    aria-hidden="true"
                ></i>
                <span :class="collapsed ? 'sr-only' : 'truncate'">
                    {{ collapsed ? 'Expand navigation' : 'Collapse navigation' }}
                </span>
            </button>
        </div>
    </aside>

    <Drawer
        :id="panelId"
        v-model:visible="open"
        position="left"
        modal
        dismissable
        block-scroll
        header="Navigation"
        class="w-80 max-w-[85vw]"
    >
        <SidebarNav
            :groups="groups"
            :current-path="currentPath"
            :collapsed="collapsed"
            @navigate="open = false"
        />

        <!-- Same control as the rail footer, not a second idea: the drawer is
             the only navigation a phone sees, so the collapse arrow has to
             exist there too. It drives the same persisted state, which means a
             choice made on mobile is what the desktop rail opens with.

             Deliberately in the default slot, not `#footer`: PrimeVue focuses
             the first focusable element of the footer slot when the drawer
             opens, which would land on "Collapse navigation" instead of the
             close button. -->
        <div class="mt-5 border-t border-aq-shell-line pt-3">
            <button
                type="button"
                class="flex h-10 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-surface-600 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
                :aria-expanded="!collapsed"
                :aria-controls="panelId"
                @click="collapsed = !collapsed"
            >
                <i
                    class="pi shrink-0 text-base"
                    :class="collapsed ? 'pi-angle-double-right' : 'pi-angle-double-left'"
                    aria-hidden="true"
                ></i>
                <span class="truncate">
                    {{ collapsed ? 'Expand navigation' : 'Collapse navigation' }}
                </span>
            </button>
        </div>
    </Drawer>
</template>
