<script setup lang="ts">
import { useId } from 'vue';

/**
 * A titled block inside a long form.
 *
 * Complex CRM records (account, contact, opportunity) are edited in sections,
 * not one wall of inputs: the heading gives the screen-reader a landmark, and
 * the grid keeps related fields on one line without a bespoke layout per page.
 *
 * The section is presentation only. It owns no state and does not touch the
 * form; a child that needs full width uses `class="sm:col-span-2"`.
 */

interface Props {
    title: string;
    description?: string;
    columns?: 1 | 2 | 3;
    /** A rule above the section. Off by default; turn on for long forms. */
    divided?: boolean;
}

const { title, description, columns = 2, divided = false } = defineProps<Props>();

const headingId = useId();
</script>

<template>
    <section
        class="flex flex-col gap-4"
        :class="divided ? 'border-t border-content-border pt-5 first:border-t-0 first:pt-0' : ''"
        :aria-labelledby="headingId"
    >
        <div class="flex flex-col gap-1">
            <h2
                :id="headingId"
                class="text-base font-semibold text-surface-900 dark:text-surface-0"
            >
                {{ title }}
            </h2>

            <p v-if="description" class="text-sm text-surface-600 dark:text-surface-300">
                {{ description }}
            </p>
        </div>

        <div
            class="grid gap-4"
            :class="{
                'sm:grid-cols-2': columns === 2,
                'sm:grid-cols-2 lg:grid-cols-3': columns === 3,
            }"
        >
            <slot />
        </div>
    </section>
</template>
