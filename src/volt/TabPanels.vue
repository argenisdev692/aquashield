<!--
    Local patch against the `volt-vue add TabPanels` output: the generated root
    paints a `bg-surface-0` surface and an inset (pt/pb/px). That surface is
    what fused a tab panel to the tab strip above it — two white blocks with no
    gap between them — and the inset made the panel content narrower than the
    page column it belongs to.

    The panels now carry no surface and no inset. The page owns both: a panel
    that wants breathing room asks for it (`mt-6`), a panel that wants to be a
    card asks for that instead, and a panel that wants the default inset says
    so on its own `TabPanel`.
-->
<template>
    <TabPanels
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge,
        }"
    >
        <slot></slot>
    </TabPanels>
</template>

<script setup lang="ts">
import TabPanels, {
    type TabPanelsPassThroughOptions,
    type TabPanelsProps,
} from 'primevue/tabpanels';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ TabPanelsProps {}
defineProps<Props>();

const theme = ref<TabPanelsPassThroughOptions>({
    root: `text-surface-700 dark:text-surface-0 outline-none`,
});
</script>
