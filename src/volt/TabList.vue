<!--
    Local patch against the `volt-vue add TabList` output (v4-era template):
    PrimeVue 5's TabList pt exposes root/prevButton/nextButton/content/activeBar
    and no longer has a `tabList` node, so its classes moved onto `content`.
    The ink bar is hidden — v5 positions it with --px-active-bar-* variables the
    template does not consume, and the active Tab already draws its own border.
-->
<template>
    <TabList
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge,
        }"
    >
        <slot></slot>
    </TabList>
</template>

<script setup lang="ts">
import TabList, { type TabListPassThroughOptions, type TabListProps } from 'primevue/tablist';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ TabListProps {}
defineProps<Props>();

const navButton = `!absolute flex-shrink-0 top-0 z-20 h-full flex items-center justify-center cursor-pointer
        bg-surface-0 dark:bg-surface-900 text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-surface-0 w-10
        shadow-[0px_0px_10px_50px_rgba(255,255,255,0.6)] dark:shadow-[0px_0px_10px_50px] dark:shadow-surface-900/50
        focus-visible:z-10 focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-[-1px] focus-visible:outline-primary
        transition-colors duration-200`;

const theme = ref<TabListPassThroughOptions>({
    root: `flex relative`,
    prevButton: navButton + ` start-0`,
    nextButton: navButton + ` end-0`,
    content: `relative flex flex-grow overflow-x-auto overscroll-x-contain scroll-smooth [scrollbar-width:none]
        bg-surface-0 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-700`,
    // The ink bar is decorative (aria-hidden) and PrimeVue 5 drives it with
    // --px-active-bar-* custom properties the v4-era generator template does
    // not consume. The active Tab already draws its own bottom border, so the
    // bar is hidden instead of shipping a full-height block over the label.
    activeBar: `hidden`,
});
</script>
