<script setup lang="ts">
/**
 * Branded auth shell shared by all seven Fortify screens.
 *
 * Always dark, by decision. The brand lockup is the white logo
 * (/img/logo-white.webp): white artwork on a light surface is invisible — the
 * shell pins the night navy (`aq-void`) and carries its own `.dark` scope so
 * every Volt control
 * inside renders its dark variant regardless of the visitor's stored theme.
 * The sign-in flow is the one place the product can afford to look the same
 * for everyone.
 *
 * Same navy field, blueprint grid and aqua accent as the marketing pages, so
 * the flow reads as the same company. The panel stays a single column with
 * one primary action: auth best practice is fewer choices, not more. The
 * brand slot defaults to the white lockup linking home, the heading is
 * centred over the panel, and the form below stays left-aligned. CursorOrb
 * rides along so the flow feels like the rest of the site.
 */
import { computed } from 'vue';
import CookieConsentBanner from '@/Components/CookieConsentBanner.vue';
import AppFooter from '@/Components/AppFooter.vue';
import CursorOrb from '@/Components/CursorOrb.vue';

/*
 * Public route, not a bundled import: an absolute path in a static `src` is
 * rewritten as a module id by the Vue asset transform and fails the build as
 * UNRESOLVED_IMPORT, so the path travels as a runtime binding.
 */
const logoWhite = '/img/logo-white.webp';

const { width = 'sm' } = defineProps<{
    title: string;
    subtitle?: string;
    /**
     * Panel width. `md` is for the sign-in tabs: three labels plus their
     * padding do not fit the narrow column, and a scrollable tab strip on the
     * first screen is the wrong first impression.
     */
    width?: 'sm' | 'md';
}>();

/*
 * Written as literal class strings so Tailwind's scanner sees both: a computed
 * `max-w-${width}` would be dropped from the build.
 */
const panelWidths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
} as const;

const panelWidth = computed(() => panelWidths[width]);
</script>

<template>
    <main
        class="dark relative isolate flex min-h-screen w-full flex-col items-center justify-center overflow-hidden bg-aq-void p-6 [color-scheme:dark]"
    >
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div
                class="absolute -top-40 left-1/2 h-120 w-192 -translate-x-1/2 rounded-full bg-aq-accent/10 blur-[120px] animate-[aq-orb-drift_24s_ease-in-out_infinite]"
            ></div>
            <div class="aq-grid-overlay absolute inset-0"></div>
        </div>

        <div class="w-full" :class="panelWidth">
            <div class="mb-6 flex justify-center">
                <slot name="brand">
                    <a
                        href="/"
                        class="group flex items-center rounded-lg transition-opacity duration-200 hover:opacity-85 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-aq-accent"
                        aria-label="AquaShield Restoration USA — home"
                    >
                        <span
                            class="aq-logo-shine transition-transform duration-200 group-hover:-translate-y-0.5 motion-reduce:transform-none motion-reduce:transition-none"
                        >
                            <img
                                :src="logoWhite"
                                alt=""
                                width="172"
                                height="212"
                                class="h-24 w-auto shrink-0 object-contain"
                            />
                        </span>
                    </a>
                </slot>
            </div>

            <div
                class="rounded-2xl border border-aq-line bg-aq-elevated/35 p-6 text-aq-ink-soft shadow-xl backdrop-blur-xl sm:p-7"
            >
                <div class="text-center">
                    <h1 class="text-xl font-semibold text-aq-ink">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1 text-sm text-aq-ink-muted">{{ subtitle }}</p>
                </div>

                <div class="mt-6">
                    <slot />
                </div>
            </div>

            <div class="mt-4 text-center text-sm text-aq-ink-soft">
                <slot name="footer" />
            </div>
        </div>

        <AppFooter class="mt-6 w-full max-w-6xl" />

        <CookieConsentBanner />
        <CursorOrb />
    </main>
</template>
