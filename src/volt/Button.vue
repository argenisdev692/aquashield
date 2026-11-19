<template>
    <Button
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge,
        }"
    >
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Button>
</template>

<script setup lang="ts">
import Button, { type ButtonPassThroughOptions, type ButtonProps } from 'primevue/button';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ ButtonProps {}
defineProps<Props>();

const theme = ref<ButtonPassThroughOptions>({
    root: `inline-flex cursor-pointer select-none items-center justify-center overflow-hidden relative
        px-3 py-2 gap-2 rounded-md disabled:pointer-events-none disabled:opacity-60 transition-colors duration-200
        bg-primary enabled:hover:bg-primary-emphasis enabled:active:bg-primary-emphasis-alt text-primary-contrast
        border border-primary enabled:hover:border-primary-emphasis enabled:active:border-primary-emphasis-alt
        focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-primary
        p-vertical:flex-col p-fluid:w-full p-fluid:p-icon-only:w-10
        p-icon-only:w-10 p-icon-only:px-0 p-icon-only:gap-0
        p-icon-only:p-rounded:rounded-full p-icon-only:p-rounded:h-10
        p-small:text-sm p-small:px-[0.625rem] p-small:py-[0.375rem]
        p-large:text-[1.125rem] p-large:px-[0.875rem] p-large:py-[0.625rem]
        p-raised:shadow-sm p-rounded:rounded-[2rem]
        p-outlined:bg-transparent enabled:hover:p-outlined:bg-primary-50 enabled:active:p-outlined:bg-primary-100
        p-outlined:border-primary-200 enabled:hover:p-outlined:border-primary-200 enabled:active:p-outlined:border-primary-200
        p-outlined:text-primary enabled:hover:p-outlined:text-primary enabled:active:p-outlined:text-primary
        dark:p-outlined:bg-transparent dark:enabled:hover:p-outlined:bg-primary/5 dark:enabled:active:p-outlined:bg-primary/15
        dark:p-outlined:border-primary-700 dark:enabled:hover:p-outlined:border-primary-700 dark:enabled:active:p-outlined:border-primary-700
        dark:p-outlined:text-primary dark:enabled:hover:p-outlined:text-primary dark:enabled:active:p-outlined:text-primary
        p-text:bg-transparent enabled:hover:p-text:bg-primary-50 enabled:active:p-text:bg-primary-100
        p-text:border-transparent enabled:hover:p-text:border-transparent enabled:active:p-text:border-transparent
        p-text:text-primary enabled:hover:p-text:text-primary enabled:active:p-text:text-primary
        dark:p-text:bg-transparent dark:enabled:hover:p-text:bg-primary/5 dark:enabled:active:p-text:bg-primary/15
        dark:p-text:border-transparent dark:enabled:hover:p-text:border-transparent dark:enabled:active:p-text:border-transparent
        dark:p-text:text-primary dark:enabled:hover:p-text:text-primary dark:enabled:active:p-text:text-primary

        p-secondary:bg-surface-100 enabled:hover:p-secondary:bg-surface-200 enabled:active:p-secondary:bg-surface-300
        p-secondary:border-surface-100 enabled:hover:p-secondary:border-surface-200 enabled:active:p-secondary:border-surface-300
        p-secondary:text-surface-700 dark:p-secondary:bg-surface-800 dark:enabled:hover:p-secondary:bg-surface-700
        dark:p-secondary:text-surface-100
        p-outlined:p-secondary:bg-transparent p-outlined:p-secondary:border-surface-200
        p-outlined:p-secondary:text-surface-600 dark:p-outlined:p-secondary:border-surface-700
        dark:p-outlined:p-secondary:text-surface-300
        p-text:p-secondary:bg-transparent p-text:p-secondary:text-surface-600
        enabled:hover:p-text:p-secondary:bg-surface-100 dark:p-text:p-secondary:text-surface-300
        dark:enabled:hover:p-text:p-secondary:bg-surface-800

        p-danger:bg-aq-danger enabled:hover:p-danger:bg-aq-danger-strong enabled:active:p-danger:bg-aq-danger-strong
        p-danger:border-aq-danger enabled:hover:p-danger:border-aq-danger-strong enabled:active:p-danger:border-aq-danger-strong
        p-danger:text-surface-0
        p-outlined:p-danger:bg-transparent p-outlined:p-danger:border-aq-danger/50 p-outlined:p-danger:text-aq-danger-strong
        enabled:hover:p-outlined:p-danger:bg-aq-danger/10 enabled:hover:p-outlined:p-danger:border-aq-danger/70
        dark:p-outlined:p-danger:text-aq-danger
        p-text:p-danger:bg-transparent p-text:p-danger:text-aq-danger-strong
        enabled:hover:p-text:p-danger:bg-aq-danger/10 dark:p-text:p-danger:text-aq-danger
        dark:enabled:hover:p-text:p-danger:bg-aq-danger/15
    `,
    loadingIcon: `animate-spin`,
    icon: `p-right:order-1 p-bottom:order-2`,
    label: `font-medium p-icon-only:invisible p-icon-only:w-0
        p-small:text-sm p-large:text-[1.125rem]`,
    pcBadge: {
        root: `min-w-4 h-4 leading-4 bg-primary-contrast rounded-full text-primary text-xs font-bold`,
    },
});
</script>
