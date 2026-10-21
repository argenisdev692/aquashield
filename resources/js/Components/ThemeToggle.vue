<script setup lang="ts">
import { useTheme } from '@/lib/theme';

/**
 * Icon-only theme switch. State and persistence live in `useTheme` so the
 * command palette's "switch to light/dark" entry can never disagree with the
 * `aria-pressed` state rendered here.
 */
const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        class="inline-flex size-9 items-center justify-center rounded-lg border border-content-border bg-surface-0 text-surface-700 transition-colors duration-200 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
        :aria-pressed="isDark"
        aria-label="Toggle dark mode"
        @click="toggleTheme"
    >
        <svg
            v-if="isDark"
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="size-4"
        >
            <circle cx="12" cy="12" r="4" />
            <path
                d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
            />
        </svg>
        <svg
            v-else
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="size-4"
        >
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
        </svg>
    </button>
</template>
