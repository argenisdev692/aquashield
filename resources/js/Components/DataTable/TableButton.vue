<script setup lang="ts">
/**
 * The kit's one button primitive.
 *
 * Volt's Button is the right choice for a branded call to action, but a dense
 * table toolbar needs a neutral 36px control with a 3:1 border. Repeating that
 * class list in the search, filters, export, bulk and pagination components is
 * knowledge duplication — the border token and focus treatment would drift.
 *
 * The icon-only mode deliberately leaves the accessible name to the caller:
 * every call site passes `aria-label` (and usually a tooltip).
 */
import { computed } from 'vue';

type ButtonVariant = 'neutral' | 'primary' | 'danger' | 'ghost';
type ButtonSize = 'sm' | 'md';

interface Props {
    variant?: ButtonVariant;
    size?: ButtonSize;
    iconOnly?: boolean;
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
}

const props = defineProps<Props>();

defineSlots<{ default(): unknown }>();

const VARIANT_CLASS: Record<ButtonVariant, string> = {
    neutral:
        'border-content-border bg-surface-0 text-surface-700 hover:bg-surface-100 hover:text-surface-900 dark:bg-surface-900 dark:text-surface-100 dark:hover:bg-surface-800 dark:hover:text-surface-0',
    primary:
        'border-primary bg-primary text-primary-contrast hover:border-primary-emphasis hover:bg-primary-emphasis',
    danger: 'border-aq-danger-strong/40 bg-transparent text-aq-danger-strong hover:bg-aq-danger-strong/10',
    ghost: 'border-transparent bg-transparent text-surface-600 hover:bg-surface-100 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0',
};

const SIZE_CLASS: Record<ButtonSize, string> = {
    sm: 'h-8 gap-1.5 px-2 text-xs',
    md: 'h-9 gap-2 px-3 text-sm',
};

const classes = computed(() => [
    'inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md border font-medium',
    'transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
    'disabled:pointer-events-none disabled:opacity-50 motion-reduce:transition-none',
    VARIANT_CLASS[props.variant ?? 'neutral'],
    SIZE_CLASS[props.size ?? 'md'],
    props.iconOnly ? (props.size === 'sm' ? 'w-8 px-0' : 'w-9 px-0') : '',
]);
</script>

<template>
    <button :type="props.type ?? 'button'" :disabled="props.disabled" :class="classes">
        <slot />
    </button>
</template>
