<script setup lang="ts">
import Button from '@/volt/Button.vue';

/**
 * The form kit's primary button.
 *
 * Volt's Button already owns the token palette and the disabled/focus states;
 * this wrapper adds the one thing a CRUD form needs consistently — a hover
 * treatment that is not a colour flash. A sheen sweeps once on hover and the
 * surface lifts 1px. Both are disabled under `prefers-reduced-motion`.
 *
 * `loading` is rendered here rather than passed to Volt because Button's
 * `loading` prop is deprecated in v5.
 */

interface Props {
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'button',
    loading: false,
    disabled: false,
});

defineOptions({ inheritAttrs: false });
</script>

<template>
    <Button
        v-bind="$attrs"
        :type="props.type"
        :disabled="props.disabled || props.loading"
        :aria-busy="props.loading || undefined"
        class="aq-btn-hover"
    >
        <span class="relative z-10 inline-flex items-center justify-center gap-2">
            <i
                v-if="props.loading"
                class="pi pi-spinner animate-spin text-sm motion-reduce:animate-none"
                aria-hidden="true"
            />
            <slot />
        </span>
    </Button>
</template>
