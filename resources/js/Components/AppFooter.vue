<script setup lang="ts">
/**
 * AppFooter — the slim legal/copyright line shared by every app shell.
 *
 * One component, all shells: the `surface`/`dark:` token pair lets it adapt
 * with no variant prop — AppLayout takes its scope from the html `.dark`
 * theme toggle, AuthLayout pins its own `.dark` on the shell root, so both
 * render the same classes as the right mode. The entry template keeps its
 * own marketing footer (WelcomeFooter) — this leaves that brand block alone.
 *
 * The year is runtime-derived, never a literal: a hard-coded year goes stale
 * (the Tailwind docs site shipped exactly that — tailwindcss.com issue 2434, "footer
 * copyright year not updated to 2026"). This app renders client-side
 * (Inertia SSR disabled), so a runtime year is always the current year.
 *
 * The legal pages live on the official site and are linked, never copied —
 * one maintained policy beats two that drift (the same decision
 * WelcomeFooter documents). They are external links, so they carry
 * `target=_blank` + `rel="noopener noreferrer"` and the new-tab hint rides
 * on `aria-label` — safer than an `sr-only` span, which leaks into the
 * layout as visible text the moment a CSS build misses one utility.
 */
interface FooterLink {
    readonly label: string;
    readonly href: string;
}

const LEGAL_LINKS: readonly FooterLink[] = [
    { label: 'Privacy Policy', href: 'https://aquashieldrestorationusa.com/privacy-policy' },
    {
        label: 'Terms & Conditions',
        href: 'https://aquashieldrestorationusa.com/terms-and-conditions',
    },
];

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-aq-shell-line">
        <div
            class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-2 px-6 py-4 text-xs text-surface-500 sm:flex-row sm:px-8 dark:text-surface-400"
        >
            <p>© {{ year }} AquaShield Restoration USA · All rights reserved</p>
            <nav aria-label="Legal" class="flex items-center gap-4">
                <a
                    v-for="link in LEGAL_LINKS"
                    :key="link.label"
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    :aria-label="`${link.label} (opens in a new tab)`"
                    class="rounded-md font-medium transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none dark:hover:text-surface-200"
                >
                    {{ link.label }}
                </a>
            </nav>
        </div>
    </footer>
</template>
