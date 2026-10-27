<script setup lang="ts">
import Button from '@/volt/Button.vue';

/**
 * The form kit's primary button.
 *
 * Volt's Button already owns the token palette and the disabled/focus states;
 * this wrapper adds the one thing a CRUD form needs consistently — motion that
 * is not a colour flash.
 *
 * Two treatments, deliberately exclusive:
 * - default (`aq-btn-hover`): one sheen crosses the pill on hover plus a 1px
 *   lift — feedback for any submit button in a form.
 * - `shine` (`aq-btn-shine`): the view's single primary CTA. A glare sweeps the
 *   face on a loop and a conic comet laps the 1px border. Never stack both:
 *   the two classes both own `::before`, so the later rule wins half the
 *   properties and the sweep freezes.
 *
 * `loading` is rendered here rather than passed to Volt because Button's
 * `loading` prop is deprecated in v5.
 */

interface Props {
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
    /** Opt into the looping CTA shine for the one primary action on a view. */
    shine?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'button',
    loading: false,
    disabled: false,
    shine: false,
});

defineOptions({ inheritAttrs: false });
</script>

<template>
    <Button
        v-bind="$attrs"
        :type="props.type"
        :disabled="props.disabled || props.loading"
        :aria-busy="props.loading || undefined"
        :class="props.shine ? 'aq-btn-shine' : 'aq-btn-hover'"
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
