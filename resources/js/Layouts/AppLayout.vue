<script setup lang="ts">
/**
 * App shell — header, primary rail, mobile drawer, palette.
 *
 * Anatomy follows what shipped SaaS headers converged on: a navigation trigger
 * on small screens, brand + workspace on the left, global search as a command
 * palette trigger, and a compact utility cluster on the right. The rail and the
 * drawer are the same component (`SidebarNav`) rendered at two breakpoints.
 *
 * Navigation is one model (`@/lib/navigation`): the rail, the drawer and the
 * palette all read the same destinations, so a route can never be reachable in
 * one surface and missing from another. The palette is the only keyboard
 * surface the app adds — ⌘/Ctrl-K opens it everywhere, and every entry is a real
 * destination or a real action.
 */
import { computed, onMounted, onUnmounted, ref, useId, useTemplateRef } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppFooter from '@/Components/AppFooter.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import CookieConsentBanner from '@/Components/CookieConsentBanner.vue';
import CursorOrb from '@/Components/CursorOrb.vue';
import HeaderInbox from '@/Components/HeaderInbox.vue';
import SessionIdleWarning from '@/Components/SessionIdleWarning.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import type { Command } from '@/lib/commands';
import {
    DASHBOARD_PATH,
    DASHBOARD_SECTIONS,
    PRIMARY_NAV,
    jumpToSection,
    sectionId,
} from '@/lib/navigation';
import { useTheme } from '@/lib/theme';
import Avatar from '@/volt/Avatar.vue';
import ConfirmDialog from '@/volt/ConfirmDialog.vue';
import Menu from '@/volt/Menu.vue';
import Toast from '@/volt/Toast.vue';

defineProps<{
    title: string;
}>();

interface AuthUser {
    name: string;
    email: string;
    profile_photo_url?: string | null;
}

/**
 * Structural subset of PrimeVue's `MenuItem`. The architecture suite bans a
 * direct PrimeVue import outside `src/volt/`, and a type-only import counts:
 * the wrapper's `model` prop is the contract, not the upstream type name.
 */
interface AccountMenuItem {
    label?: string;
    icon?: string;
    separator?: boolean;
    disabled?: boolean;
    command?: () => void;
}

/**
 * `user` is a page prop of the dashboard route, not a shared Inertia prop, so
 * it is typed here as optional: a future page that omits it simply renders the
 * plain sign-out control instead of an empty avatar.
 */
const page = usePage<{ user?: AuthUser | null }>();

const { isDark, toggleTheme } = useTheme();

const paletteOpen = ref(false);
const navOpen = ref(false);
const navPanelId = useId();
const accountMenuOpen = ref(false);
const accountMenu = useTemplateRef<{ toggle: (event: Event) => void }>('accountMenu');

/**
 * Bound rather than a literal `src`: Vite 8 treats a literal public path in a
 * template as a module request and fails the build.
 */
const logoMark = '/img/logo-mark.webp';

const user = computed(() => page.props.user ?? null);
const idleConfig = computed(() => page.props.authIdle);
const currentPath = computed(() => page.url.split('?')[0] ?? '/');

const initials = computed(() => {
    const parts = user.value?.name.trim().split(/\s+/).filter(Boolean) ?? [];

    return (
        parts
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'AS'
    );
});

const commands = computed<Command[]>(() => {
    const destinations: Command[] = PRIMARY_NAV.map((item) => ({
        id: `nav-${item.href.slice(1)}`,
        group: 'Go to',
        label: item.label,
        icon: item.icon,
        keywords: item.keywords,
        run: () => router.visit(item.href),
    }));

    /**
     * Section jumps are real only while the dashboard is mounted. Gating here —
     * rather than rendering a dead entry — keeps the palette honest on the auth
     * screens, which share this shell's palette.
     */
    const sections: Command[] =
        currentPath.value === DASHBOARD_PATH
            ? DASHBOARD_SECTIONS.map((item) => ({
                  id: `jump-${sectionId(item.href)}`,
                  group: 'On this page',
                  label: item.label,
                  icon: item.icon,
                  keywords: item.keywords,
                  run: () => jumpToSection(sectionId(item.href)),
              }))
            : [];

    return [
        ...destinations,
        ...sections,
        {
            id: 'toggle-theme',
            group: 'Preferences',
            label: isDark.value ? 'Switch to light mode' : 'Switch to dark mode',
            icon: isDark.value ? 'pi-sun' : 'pi-moon',
            keywords: 'theme appearance colour color dark light',
            run: toggleTheme,
        },
        {
            id: 'profile',
            group: 'Preferences',
            label: 'Profile & security',
            icon: 'pi-user-edit',
            keywords: 'account email password 2fa two-factor recovery security',
            run: () => router.visit('/settings/profile'),
        },
        {
            id: 'sign-out',
            group: 'Session',
            label: 'Sign out',
            icon: 'pi-sign-out',
            keywords: 'logout leave',
            run: () => router.post('/logout'),
        },
    ];
});

const accountItems = computed<AccountMenuItem[]>(() => {
    const items: AccountMenuItem[] = [];

    if (user.value) {
        items.push({ label: user.value.email, disabled: true });
    }

    items.push({ separator: true });
    items.push({
        label: 'Profile & security',
        icon: 'pi pi-user-edit',
        command: () => router.visit('/settings/profile'),
    });
    items.push({ separator: true });
    items.push({
        label: 'Sign out',
        icon: 'pi pi-sign-out',
        command: () => router.post('/logout'),
    });

    return items;
});

const onKeydown = (event: KeyboardEvent): void => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        paletteOpen.value = !paletteOpen.value;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-surface-50 text-surface-700 dark:bg-surface-950 dark:text-surface-0"
    >
        <a
            href="#main"
            class="sr-only rounded-lg bg-surface-0 px-4 py-2 text-sm font-medium text-surface-900 focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 dark:bg-surface-900 dark:text-surface-0"
        >
            Skip to content
        </a>

        <header
            class="sticky top-0 z-40 border-b border-aq-shell-line bg-surface-0/75 backdrop-blur-xl dark:bg-surface-950/70"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-384 items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8"
            >
                <button
                    type="button"
                    class="-ms-1 grid size-10 shrink-0 place-items-center rounded-lg text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none lg:hidden dark:text-surface-100 dark:hover:bg-surface-800"
                    aria-label="Open navigation"
                    :aria-controls="navPanelId"
                    :aria-expanded="navOpen"
                    @click="navOpen = true"
                >
                    <i class="pi pi-bars text-base" aria-hidden="true" />
                </button>

                <Link
                    href="/dashboard"
                    aria-label="AquaShield dashboard"
                    class="group flex items-center gap-2.5 rounded-xl pr-1 transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    <img :src="logoMark" alt="" class="h-8 w-auto" />
                    <span class="hidden flex-col leading-tight sm:flex">
                        <span
                            class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-0"
                        >
                            AquaShield
                        </span>
                        <span
                            class="hidden text-[11px] font-medium text-surface-500 sm:inline dark:text-surface-400"
                        >
                            CRM workspace
                        </span>
                    </span>
                </Link>

                <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                    <button
                        type="button"
                        class="hidden h-9 w-52 items-center gap-2 rounded-lg border border-content-border bg-surface-0 px-3 text-left text-sm text-surface-500 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 md:flex dark:bg-surface-800/60 dark:text-surface-300 dark:hover:bg-surface-800 motion-reduce:transition-none"
                        aria-label="Open command palette"
                        aria-keyshortcuts="Control+K Meta+K"
                        @click="paletteOpen = true"
                    >
                        <i class="pi pi-search text-xs" aria-hidden="true" />
                        <span>Search…</span>
                        <kbd
                            class="ml-auto inline-flex h-5 items-center rounded border border-surface-200 bg-surface-50 px-1.5 font-sans text-[10px] font-medium text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                        >
                            Ctrl K
                        </kbd>
                    </button>

                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-lg border border-content-border bg-surface-0 text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 md:hidden dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
                        aria-label="Open command palette"
                        aria-keyshortcuts="Control+K Meta+K"
                        @click="paletteOpen = true"
                    >
                        <i class="pi pi-search text-sm" aria-hidden="true" />
                    </button>

                    <HeaderInbox kind="notifications" />
                    <HeaderInbox kind="messages" />

                    <span
                        aria-hidden="true"
                        class="hidden h-6 w-px bg-surface-200 sm:block dark:bg-surface-700"
                    ></span>

                    <ThemeToggle />

                    <button
                        v-if="user"
                        type="button"
                        class="flex items-center gap-1.5 rounded-full p-0.5 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:hover:bg-surface-800 motion-reduce:transition-none"
                        aria-haspopup="menu"
                        :aria-expanded="accountMenuOpen"
                        aria-label="Account menu"
                        @click="accountMenu?.toggle($event)"
                    >
                        <img
                            v-if="user.profile_photo_url"
                            :src="user.profile_photo_url"
                            alt=""
                            class="size-9 rounded-full border border-aq-line object-cover"
                        />
                        <Avatar
                            v-else
                            :label="initials"
                            shape="circle"
                            class="bg-aq-accent/15 text-xs font-semibold text-aq-accent dark:bg-aq-accent/20"
                        />
                        <i
                            class="pi pi-chevron-down hidden text-[10px] text-surface-500 sm:inline dark:text-surface-400"
                            aria-hidden="true"
                        />
                    </button>
                    <Link
                        v-else
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

        <div class="mx-auto flex w-full max-w-384 flex-1 items-stretch">
            <AppSidebar v-model:open="navOpen" :current-path="currentPath" :panel-id="navPanelId" />

            <div class="flex min-w-0 flex-1 flex-col">
                <main id="main" class="w-full flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    <h1
                        class="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-0"
                    >
                        {{ title }}
                    </h1>
                    <div class="mt-6">
                        <slot />
                    </div>
                </main>

                <AppFooter />
            </div>
        </div>

        <Menu
            ref="accountMenu"
            :model="accountItems"
            popup
            @show="accountMenuOpen = true"
            @hide="accountMenuOpen = false"
        />
        <CommandPalette v-model="paletteOpen" :commands="commands" />

        <Toast position="bottom-right" />
        <ConfirmDialog />

        <SessionIdleWarning
            v-if="idleConfig"
            :warn-after-seconds="idleConfig.warnAfterSeconds"
            :grace-seconds="idleConfig.graceSeconds"
            :keep-alive-endpoint="idleConfig.keepAliveEndpoint"
            :logout-endpoint="idleConfig.logoutEndpoint"
        />

        <CookieConsentBanner />
        <CursorOrb />
    </div>
</template>
