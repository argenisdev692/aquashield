<script setup lang="ts">
/**
 * SettingsCard — the one surface every settings section shares.
 *
 * A quiet header (icon, title, description, optional action), a content slot
 * and an optional footer. Keeping one card primitive stops the four tabs from
 * drifting into four different border/radius/shadow decisions.
 *
 * The call site supplies a PrimeIcons class rather than a VNode, so the header
 * stays a plain template and the component needs no render function.
 */
defineProps<{
    title: string;
    description?: string;
    /** PrimeIcons name including the `pi ` prefix, e.g. `pi pi-user`. */
    icon?: string;
}>();

defineSlots<{
    default?: () => unknown;
    action?: () => unknown;
    footer?: () => unknown;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-aq-shell-line bg-surface-0 shadow-sm dark:bg-surface-900"
    >
        <header
            class="flex flex-wrap items-start gap-3 border-b border-aq-shell-line/70 px-5 py-4 sm:px-6"
        >
            <span
                v-if="icon"
                class="grid size-9 shrink-0 place-items-center rounded-xl bg-aq-accent/10 text-aq-accent"
                aria-hidden="true"
            >
                <i class="pi text-sm" :class="icon" />
            </span>

            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                    {{ title }}
                </h2>

                <p v-if="description" class="mt-0.5 text-sm text-surface-600 dark:text-surface-300">
                    {{ description }}
                </p>
            </div>

            <div v-if="$slots.action" class="shrink-0">
                <slot name="action" />
            </div>
        </header>

        <div class="px-5 py-5 sm:px-6">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-aq-shell-line/70 bg-surface-50/60 px-5 py-3 sm:px-6 dark:bg-surface-950/30"
        >
            <slot name="footer" />
        </div>
    </section>
</template>
