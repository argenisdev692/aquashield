<script setup lang="ts">
import { computed } from 'vue';
import type { FormErrorMap } from '@/lib/forms';

/**
 * A submit-time error summary, announced once and linked to the real controls.
 *
 * Inline errors already sit next to each field; the summary exists for the
 * case a screen-reader user never reaches them: a long form, a server 422, or a
 * keyboard user who submitted with Enter from the last field. `role="alert"`
 * announces the block, and each item focuses its control instead of scrolling
 * to an arbitrary anchor.
 */

interface Props {
    errors: FormErrorMap;
    formEl?: HTMLFormElement | null;
}

const props = defineProps<Props>();

const entries = computed<[string, string][]>(() =>
    Object.entries(props.errors).filter((entry): entry is [string, string] => {
        return entry[1] !== undefined;
    }),
);

const focus = (name: string): void => {
    const selector = `[name="${name}"]`;
    const target =
        props.formEl?.querySelector<HTMLElement>(selector) ??
        document.querySelector<HTMLElement>(selector);

    target?.focus();
};
</script>

<template>
    <div
        v-if="entries.length > 0"
        role="alert"
        tabindex="-1"
        class="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2.5 text-sm"
    >
        <p class="font-medium text-aq-danger-strong">Please fix the following:</p>

        <ul class="mt-1 list-disc space-y-0.5 ps-5">
            <li v-for="[name, message] in entries" :key="name">
                <a
                    :href="`#${name}`"
                    class="text-aq-danger-strong underline underline-offset-2"
                    @click.prevent="focus(name)"
                >
                    {{ message }}
                </a>
            </li>
        </ul>
    </div>
</template>
