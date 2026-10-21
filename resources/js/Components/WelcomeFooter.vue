<script setup lang="ts">
/**
 * Page-level footer for the entry template.
 *
 * The legal pages live on the official site and are linked, never copied —
 * one maintained policy beats two that drift. They are external links, so
 * they carry `target=_blank` + `rel="noopener noreferrer"` (keeps the entry
 * session alive, pins the tabnabbing hole shut), an arrow-up-right icon so
 * the off-site nature is visible, and the new-tab hint rides on `aria-label`
 * — safer than an `sr-only` span, which leaks into the layout as visible
 * text the moment a CSS build misses one utility (seen live 2026-10-18).
 *
 * Cookie Policy sits in the row as a deliberate non-interactive placeholder:
 * the official page (`https://aquashieldrestorationusa.com/cookies-policy`)
 * still 404s (checked 2026-10-18), and a dead link is a worse trust signal
 * than a calm muted label — the same rule the social chips follow. Give it
 * an `href` below and it becomes a normal `target=_blank` link. The 2026
 * consent-compliance pattern wants a persistent footer control that re-opens
 * preferences, but this app's banner is accept-only with server-owned state
 * (CookieConsentBanner) — there is nothing to reopen yet. Wire one only when
 * a consent-preference/withdrawal endpoint ships (owasp §15.5).
 *
 * The dispatch chip reuses the hero/mockup liveness pulse verbatim
 * (`aq-pulse-ring`; globals.css stops every CSS loop under
 * `prefers-reduced-motion`, so no per-call-site modifier). Its copy quotes
 * only claims this page already publishes in its SEO description — 24/7,
 * on site within 60 minutes — so the footer and the metadata never disagree.
 *
 * The phone stays a real `tel:` action; the social chips below stay
 * non-interactive until the profiles exist (see the note above SOCIALS).
 */
const year = new Date().getFullYear();

interface FooterLink {
    readonly label: string;
    /**
     * Absolute URL once the page exists; absent = placeholder. An `<a>`
     * without `href` is not focusable, not clickable and announces as plain
     * text — never "#": that anchor opens a dead tab of this same page.
     */
    readonly href?: string;
}

/*
 * Cookie Policy: the official page still 404s (checked 2026-10-18), so it
 * renders without `href` until it ships. The other two live on the official
 * site and stay external → `_blank` + `noopener noreferrer`.
 */
const LEGAL: readonly FooterLink[] = [
    { label: 'Privacy Policy', href: 'https://aquashieldrestorationusa.com/privacy-policy' },
    {
        label: 'Terms & Conditions',
        href: 'https://aquashieldrestorationusa.com/terms-and-conditions',
    },
    { label: 'Cookie Policy' },
];

interface Social {
    readonly label: string;
    readonly href: string;
    readonly path: string;
}

/*
 * Social placeholders: the profiles don't exist yet, and a "#" anchor with
 * target=_blank is a dead button — it opens a fresh tab of this same page.
 * Until the hrefs are real these render as NON-interactive chips: no href,
 * no pointer, no new tab. When the accounts go live, restore the anchors
 * below (keep target/_blank + rel while they are external) and drop the
 * "coming soon" titles.
 */
const SOCIALS: readonly Social[] = [
    {
        label: 'Facebook',
        href: '#',
        path: 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z',
    },
    {
        label: 'Instagram',
        href: '#',
        path: 'M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077',
    },
];
</script>

<template>
    <footer class="relative bg-aq-void">
        <div
            aria-hidden="true"
            class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-aq-accent/45 to-transparent"
        ></div>
        <div class="mx-auto w-full max-w-6xl px-6 py-12 sm:px-8 lg:px-12">
            <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-sm">
                    <p class="text-sm font-bold text-aq-ink">
                        AquaShield
                        <span class="ml-2 text-xs font-medium text-aq-ink-muted">
                            Restoration USA · Houston, Texas
                        </span>
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-aq-ink-muted">
                        24/7 emergency water damage mitigation, roofing and full restoration.
                    </p>
                    <!-- Dispatch status chip: the contact block answers "are they
                         reachable right now" before the call action, not after
                         it. Copy is pulled exclusively from claims the page
                         already publishes in its SEO description. -->
                    <p
                        class="mt-5 inline-flex items-center gap-2.5 rounded-full border border-aq-line bg-aq-accent/[0.06] px-3.5 py-1.5 text-xs font-medium text-aq-ink-soft"
                    >
                        <span aria-hidden="true" class="relative flex size-2">
                            <span
                                class="absolute inline-flex size-full rounded-full bg-aq-success [animation:aq-pulse-ring_2.4s_ease-out_infinite]"
                            ></span>
                            <span
                                class="relative inline-flex size-2 rounded-full bg-aq-success"
                            ></span>
                        </span>
                        24/7 dispatch · on site within 60 minutes
                    </p>
                    <a
                        href="tel:+17135876423"
                        aria-label="24/7 emergency line: (713) 587-6423"
                        class="mt-3 inline-flex min-h-11 items-center gap-2.5 rounded-xl border border-aq-line bg-aq-accent/[0.06] px-4 text-sm font-semibold text-aq-ink transition-all duration-200 hover:-translate-y-0.5 hover:border-aq-line-strong hover:bg-aq-accent/[0.1] hover:shadow-[var(--aq-glow-accent)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent active:translate-y-0 motion-reduce:transform-none"
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
                        (713) 587-6423
                    </a>
                </div>

                <!-- Deliberately flush-left inside its column: heading and rows
                     share one edge, so every row (icon or no icon) lines up —
                     `lg:items-end` mixed a right-aligned heading with
                     left-aligned rows and read as "misaligned". -->
                <nav aria-label="Legal" class="flex flex-col">
                    <!-- Real heading (not a styled <div>): lets screen-reader
                         users jump straight to the legal group. -->
                    <h3 class="text-xs font-semibold tracking-[0.18em] text-aq-ink-muted uppercase">
                        Legal
                    </h3>
                    <ul class="mt-1.5 flex flex-col gap-1">
                        <li v-for="link in LEGAL" :key="link.label">
                            <!-- Live link: external → new tab. The new-tab hint
                                 rides on aria-label (AT-only), not an sr-only
                                 span — it can never leak into the layout. -->
                            <a
                                v-if="link.href"
                                :href="link.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                :aria-label="`${link.label} (opens in a new tab)`"
                                class="group inline-flex items-center gap-1.5 rounded-lg py-3 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:text-aq-ink hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                            >
                                {{ link.label }}
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="size-3.5 text-aq-ink-muted transition-[color,transform] duration-200 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-aq-accent motion-reduce:transform-none"
                                >
                                    <path d="M7 17 17 7M7 7h10v10" />
                                </svg>
                            </a>
                            <!-- No href yet: a muted <span> — not a dead or
                                 self-reloading link, not in the focus order. -->
                            <span
                                v-else
                                :title="`${link.label} — page coming soon`"
                                class="inline-flex cursor-default items-center py-3 text-sm font-medium text-aq-ink-muted"
                            >
                                {{ link.label }}
                            </span>
                        </li>
                    </ul>
                </nav>
            </div>

            <div
                class="mt-10 flex flex-col items-center justify-between gap-5 border-t border-aq-line pt-6 text-center sm:flex-row sm:text-left"
            >
                <p class="text-xs text-aq-ink-muted">
                    © {{ year }} AquaShield Restoration USA · Licensed &amp; insured · Direct
                    insurance billing
                </p>

                <!-- Social placeholders: interactive chrome (hover lift, glow,
                     pointer) stays deferred until the profiles exist — no dead
                     buttons. Plain `div` for now: a <nav> whose links are hidden
                     to AT announces as an EMPTY region. Restore the <nav> landmark
                     with the anchors when the accounts go live. -->
                <div aria-hidden="true" class="flex items-center gap-3">
                    <span
                        v-for="social in SOCIALS"
                        :key="social.label"
                        class="inline-flex size-9 items-center justify-center rounded-full border border-aq-line text-aq-ink-disabled opacity-70"
                        :title="`${social.label} — profile coming soon`"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" class="size-4">
                            <path :d="social.path" />
                        </svg>
                    </span>
                </div>
            </div>
        </div>
    </footer>
</template>
