<script setup lang="ts">
/**
 * Slim entry header for the 2027 welcome template.
 *
 * It points at real destinations only: the workspace anchor, the dispatch
 * line, sign in and registration. The old "Free estimate" entry dialled the
 * same number the dispatch line already covers, so its slot carries the
 * sign-in entry and "Create account" (/register) takes the last slot — every
 * auth destination renders through Fortify's guest-only views.
 *
 * Transparent over the hero, condensed to glass once the page moves. The
 * open mobile panel forces the glass state too: a disclosure floating over
 * the hero without its own surface is unreadable. Escape closes and
 * returns focus to the trigger — the keydown listener rides the document,
 * not the header, because the panel is NOT a focus trap: once focus Tabs
 * down into the hero, the header element never sees the keypress, yet the
 * disclosure it opened is still on screen and must still close.
 *
 * App routes ("/", "/login", "/register") navigate through Inertia `Link` — a plain
 * anchor here would hard-reload the SPA; `tel:` and in-page anchors stay
 * native `<a>`.
 */
import { computed, onWatcherCleanup, ref, useTemplateRef, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useWindowScroll } from '@vueuse/core';

const { company } = defineProps<{
    company: Modules.CompanyData.Application.Dtos.CompanyBrandingDto;
}>();

/*
 * The mark is bundled brand artwork (`public/img`), not the uploaded
 * company_data row: the welcome header is the one surface that must render
 * before any seed, upload or storage round trip exists. The name and the
 * dispatch number do come from the public CompanyBrandingDto, so changing the
 * company block still changes the header copy.
 *
 * Bound rather than a literal `src`: Vite 8 treats a literal public path in a
 * template as a module request and fails the build.
 */
const mark = '/img/logo-mark.webp';
const brandParts = computed(() => {
    const [first = '', ...rest] = company.name.trim().split(/\s+/);

    return { first, rest: rest.join(' ') };
});

const homeLabel = computed(() => `${company.name} — home`);

const phoneHref = computed(() => (company.phone ? `tel:${company.phone}` : '#'));

const phoneLabel = computed(() => {
    const phone = company.phone;

    if (!phone) {
        return '';
    }

    const digits = phone.replace(/\D/g, '');
    const national = digits.length === 11 && digits.startsWith('1') ? digits.slice(1) : digits;

    return national.length === 10
        ? `(${national.slice(0, 3)}) ${national.slice(3, 6)}-${national.slice(6)}`
        : phone;
});

interface HeaderLink {
    readonly href: string;
    readonly label: string;
    readonly inApp: boolean;
}

const links = computed<readonly HeaderLink[]>(() => [
    ...(company.phone
        ? [{ href: `tel:${company.phone}`, label: 'Dispatch line', inApp: false }]
        : []),
    { href: '#paths-heading', label: 'Workspace', inApp: false },
    { href: '/login', label: 'Sign in', inApp: true },
    { href: '/register', label: 'Create account', inApp: true },
]);

const { y } = useWindowScroll();
const condensed = computed(() => y.value > 16);

const menuOpen = ref(false);
const menuButton = useTemplateRef<HTMLButtonElement>('menuButton');

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
    menuOpen.value = false;
};

const onDocumentKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeMenu();
        menuButton.value?.focus();
    }
};

/* The subscription lives exactly as long as the disclosure does: onWatcher
 * Cleanup detaches it when the menu closes or the watcher stops, so a
 * closed menu leaves no listener behind. */
watch(menuOpen, (open) => {
    if (!open) {
        return;
    }

    document.addEventListener('keydown', onDocumentKeydown);
    onWatcherCleanup(() => {
        document.removeEventListener('keydown', onDocumentKeydown);
    });
});
</script>

<template>
    <header
        :class="[
            'fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,box-shadow,backdrop-filter] duration-300 ease-out motion-reduce:transition-none',
            condensed || menuOpen
                ? 'aq-glass border-b border-aq-line shadow-lg shadow-aq-void/30'
                : 'border-b border-transparent bg-transparent',
        ]"
    >
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-10 focus:rounded-lg focus:bg-aq-accent focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-aq-void"
        >
            Skip to content
        </a>

        <div
            class="mx-auto flex h-18 max-w-6xl items-center justify-between gap-6 px-6 sm:px-8 lg:px-12"
        >
            <Link
                href="/"
                class="group flex shrink-0 items-center gap-3 rounded-lg transition-opacity duration-200 hover:opacity-85 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-aq-accent"
                :aria-label="homeLabel"
            >
                <span
                    class="aq-logo-shine aq-logo-shine-mark transition-transform duration-200 group-hover:-translate-y-0.5 motion-reduce:transform-none motion-reduce:transition-none"
                >
                    <img
                        :src="mark"
                        alt=""
                        width="358"
                        height="402"
                        class="h-9 w-auto shrink-0 object-contain"
                    />
                </span>
                <span class="flex flex-col leading-none">
                    <span class="text-base font-bold tracking-tight text-aq-ink">
                        {{ brandParts.first }}
                    </span>
                    <span
                        v-if="brandParts.rest"
                        class="mt-0.5 text-[0.625rem] font-medium tracking-[0.16em] text-aq-ink-muted uppercase"
                    >
                        {{ brandParts.rest }}
                    </span>
                </span>
            </Link>

            <div class="flex items-center gap-2 sm:gap-3">
                <a
                    v-if="company.phone"
                    :href="phoneHref"
                    class="hidden items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent sm:inline-flex"
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-4 text-aq-accent"
                    >
                        <path
                            d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"
                        />
                    </svg>
                    {{ phoneLabel }}
                </a>

                <a
                    href="#paths-heading"
                    class="hidden items-center rounded-lg px-3 py-2 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent md:inline-flex"
                >
                    Workspace
                </a>

                <Link
                    href="/login"
                    class="hidden rounded-lg bg-aq-accent px-4 py-2.5 text-sm font-semibold text-aq-void transition-[background-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:bg-aq-accent-soft hover:shadow-lg hover:shadow-aq-accent/30 active:translate-y-0 active:bg-aq-accent-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transform-none motion-reduce:transition-none sm:inline-flex"
                >
                    Sign in
                </Link>

                <button
                    ref="menuButton"
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-lg border border-aq-line text-aq-ink transition-colors duration-200 hover:bg-aq-elevated/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent md:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="welcome-mobile-nav"
                    @click="toggleMenu"
                >
                    <span class="sr-only">{{ menuOpen ? 'Close menu' : 'Open menu' }}</span>
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        class="size-5"
                    >
                        <path v-if="menuOpen" d="M6 6l12 12M18 6L6 18" />
                        <path v-else d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>
            </div>
        </div>

        <Transition
            enter-active-class="transition-[opacity,transform] duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition-[opacity,transform] duration-150 ease-in"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="menuOpen"
                id="welcome-mobile-nav"
                class="border-t border-aq-line bg-aq-void/95 backdrop-blur-xl md:hidden"
            >
                <nav aria-label="Mobile" class="mx-auto max-w-6xl px-6 py-4">
                    <ul class="flex flex-col gap-1">
                        <li v-for="link in links" :key="link.href + link.label">
                            <component
                                :is="link.inApp ? Link : 'a'"
                                :href="link.href"
                                class="block rounded-lg px-3 py-2.5 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                                @click="closeMenu"
                            >
                                {{ link.label }}
                            </component>
                        </li>
                    </ul>
                </nav>
            </div>
        </Transition>
    </header>
</template>
