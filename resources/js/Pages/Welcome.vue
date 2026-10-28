<script setup lang="ts">
import { defineAsyncComponent, onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

/**
 * Entry screen for the internal operations workspace: text-only kinetic
 * hero, the floating Why AquaShield bar, the product band, the shield's
 * water moment, the scroll-narrative paths and the page footer.
 *
 * Audience is staff, not prospects. The root Blade is `noindex` and the
 * head below is an operational title, not a marketing one — social
 * previews on a staff tool are noise, and the old customer-facing OG block
 * was invisible to non-JS scrapers anyway (Inertia SSR is off).
 *
 * Every section is lazy. `app.ts` resolves pages with an EAGER
 * `import.meta.glob` over the whole Pages tree, so anything a page imports
 * STATICALLY lands in the main bundle — and the hero, the why-bar, the
 * product band, the brand moment and the paths below pull in motion-v. They
 * live in `Components/`, not a page-private folder: a nested .vue under Pages
 * is swept into that eager glob and Vite reports `INEFFECTIVE_DYNAMIC_IMPORT`.
 *
 * Two imports are deliberately off the static graph. CursorOrb is decorative
 * chrome: it waits for an idle slot and is skipped on touch and
 * reduced-motion. The cookie banner is async because it pulls the
 * Volt/PrimeVue button chunk, and consent can wait one tick — it must not be
 * a static dependency of the page chunk. The footer is lazy like the
 * sections: it sits below the fold, so its chunk never races the first paint.
 *
 * Layout deviation, documented: the checklist's `defineOptions({ layout })`
 * is not used here on purpose. The other pages slot into AuthLayout/
 * AppLayout; this one is a full-bleed entry that draws its own chrome in
 * WelcomeHeader (skip link + fixed glass bar live there), and no shared
 * GuestLayout chrome would wrap the 100svh viewport-locked hero without
 * breaking its layout. If a GuestLayout chrome ever lands, revisit.
 */
const WelcomeHeader = defineAsyncComponent(() => import('@/Components/WelcomeHeader.vue'));
const WelcomeHero = defineAsyncComponent(() => import('@/Components/WelcomeHero.vue'));
const WelcomeWhy = defineAsyncComponent(() => import('@/Components/WelcomeWhy.vue'));
const WelcomeMockup = defineAsyncComponent(() => import('@/Components/WelcomeMockup.vue'));
const WelcomeBrandMoment = defineAsyncComponent(
    () => import('@/Components/WelcomeBrandMoment.vue'),
);
const WelcomePaths = defineAsyncComponent(() => import('@/Components/WelcomePaths.vue'));
const WelcomeFooter = defineAsyncComponent(() => import('@/Components/WelcomeFooter.vue'));
const CookieConsentBanner = defineAsyncComponent(
    () => import('@/Components/CookieConsentBanner.vue'),
);
const CursorOrb = defineAsyncComponent(() => import('@/Components/CursorOrb.vue'));

/*
 * The server sends the public CompanyBrandingDto with the page (the same
 * contract as GET /api/company-data). The header owns its own presentation,
 * but the page owns the title and the prop hand-off, so both stay in sync with
 * the seeded company row.
 */
const { company } = defineProps<{
    company: Modules.CompanyData.Application.Dtos.CompanyBrandingDto;
}>();

/*
 * The orb is chrome, not content. On a phone it paints nothing (the
 * component refuses to attach a pointer listener), and for anyone who asked
 * the OS to calm motion it is noise. Gate it here and let the chunk arrive
 * after the hero has had its idle time.
 */
const orbReady = ref(false);
let cancelOrb = (): void => {};

onMounted(() => {
    if (window.matchMedia('(pointer: coarse), (prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const show = (): void => {
        orbReady.value = true;
    };

    if (window.requestIdleCallback) {
        const handle = window.requestIdleCallback(show, { timeout: 2_000 });
        cancelOrb = () => window.cancelIdleCallback(handle);
    } else {
        const handle = window.setTimeout(show, 1_200);
        cancelOrb = () => window.clearTimeout(handle);
    }
});

onUnmounted(() => {
    cancelOrb();
});
</script>

<template>
    <Head :title="`${company.name} Operations`" />

    <div class="min-h-screen bg-aq-void">
        <WelcomeHeader :company="company" />

        <main id="main">
            <WelcomeHero />
            <WelcomeWhy />
            <WelcomeMockup />
            <WelcomeBrandMoment />
            <WelcomePaths />
        </main>

        <WelcomeFooter />
        <CookieConsentBanner />
        <CursorOrb v-if="orbReady" />
    </div>
</template>
