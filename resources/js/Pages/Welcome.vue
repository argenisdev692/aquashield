<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Head } from '@inertiajs/vue3';
import CursorOrb from '@/Components/CursorOrb.vue';

/**
 * 2027 entry template: text-only kinetic hero, the floating Why AquaShield
 * bar, the product band and the scroll-narrative paths.
 *
 * Every section is lazy. `app.ts` resolves pages with an EAGER
 * `import.meta.glob` over the whole Pages tree, so anything a page imports
 * STATICALLY lands in the main bundle — and the hero, the why-bar, the
 * product band and the paths below pull in motion-v. They live in
 * `Components/`, not a page-private folder: a nested .vue under Pages is swept
 * into that eager glob and Vite reports `INEFFECTIVE_DYNAMIC_IMPORT`.
 * CursorOrb is the one static import: global chrome with no motion-v
 * dependency, so a chunk boundary would only add a flash of latency.
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
const WelcomePaths = defineAsyncComponent(() => import('@/Components/WelcomePaths.vue'));

/*
 * Absolute origin passed from routes/web.php. Hand-typed: the route prop is
 * a plain closure return, not a Spatie Data class, so `typescript:transform`
 * has nothing to generate for it. Scrapers require absolute URLs in
 * canonical/OG tags; a client-computed origin would bake in whatever host
 * rendered the page, so the server decides once.
 */
const { appUrl } = defineProps<{ appUrl: string }>();

const ogImage = computed(() => `${appUrl}/img/logo.webp`);
</script>

<template>
    <Head title="AquaShield Restoration USA | 24/7 Water Damage & Roofing, Houston TX">
        <meta
            name="description"
            content="24/7 emergency water damage mitigation, roofing and full restoration across Texas. On site within 60 minutes, licensed and insured, direct insurance billing."
        />
        <link rel="canonical" :href="appUrl" />
        <meta name="robots" content="index, follow, max-image-preview:large" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="AquaShield Restoration USA" />
        <meta property="og:locale" content="en_US" />
        <meta property="og:url" :content="appUrl" />
        <meta
            property="og:title"
            content="AquaShield Restoration USA | 24/7 Water Damage & Roofing, Houston TX"
        />
        <meta
            property="og:description"
            content="Emergency water damage mitigation, roofing and reconstruction across Texas. 60-minute response, 24/7."
        />
        <meta property="og:image" :content="ogImage" />
        <meta property="og:image:width" content="684" />
        <meta property="og:image:height" content="724" />
        <meta property="og:image:alt" content="AquaShield Restoration USA logo" />
        <meta name="twitter:card" content="summary" />
    </Head>

    <div class="min-h-screen bg-aq-void">
        <WelcomeHeader />

        <main id="main">
            <WelcomeHero />
            <WelcomeWhy />
            <WelcomeMockup />
            <WelcomePaths />
        </main>

        <CursorOrb />
    </div>
</template>
