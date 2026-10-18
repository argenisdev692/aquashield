<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import CookieConsentBanner from '@/Components/CookieConsentBanner.vue';
import CursorOrb from '@/Components/CursorOrb.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps<{
    title: string;
}>();
</script>

<template>
    <div
        class="min-h-screen bg-surface-50 text-surface-700 dark:bg-surface-950 dark:text-surface-0"
    >
        <header
            class="sticky top-0 z-40 border-b border-surface-200 bg-surface-0/80 backdrop-blur-md dark:border-surface-800 dark:bg-surface-900/80"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-6 sm:px-8"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="text-base font-bold tracking-tight text-surface-900 dark:text-surface-0"
                        >AquaShield</span
                    >
                    <span
                        class="hidden text-xs font-medium text-surface-500 dark:text-surface-400 sm:inline"
                    >
                        CRM workspace
                    </span>
                </div>

                <nav aria-label="Primary" class="hidden items-center gap-1 md:flex">
                    <Link
                        href="/dashboard"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 dark:text-surface-200 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                    >
                        Dashboard
                    </Link>
                </nav>

                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-lg border border-surface-200 bg-surface-0 px-3 py-2 text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
                    >
                        Sign out
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-6 py-8 sm:px-8">
            <h1 class="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-0">
                {{ title }}
            </h1>
            <div class="mt-6">
                <slot />
            </div>
        </main>

        <CookieConsentBanner />
        <CursorOrb />
    </div>
</template>
