<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    {{-- Favicon + PWA manifest. The assets ship under /img/favicon/, but they
        were never referenced from the head: every page fell back to the
        default /favicon.ico probe (404) and the manifest was orphaned. The
        manifest carries the dark-first brand colors matching the theme
        script below. --}}
    <link rel="icon" href="/img/favicon/favicon.svg" type="image/svg+xml" />
    <link rel="icon" href="/img/favicon/favicon-96x96.png" type="image/png" sizes="96x96" />
    <link rel="shortcut icon" href="/img/favicon/favicon.ico" />
    <link rel="apple-touch-icon" href="/img/favicon/apple-touch-icon.png" sizes="180x180" />
    <link rel="manifest" href="/img/favicon/site.webmanifest" />

    {{-- Browser chrome (mobile address bar, PWA task switcher) follows the
        active theme; the inline script below rewrites it on first paint.

        These two literals are the ONLY colours allowed to stand outside
        globals.css, because they are read before the stylesheet exists: a
        custom property cannot resolve until app.css has loaded, and the point
        of this meta is the first paint. They must stay equal to
        `--color-aq-void` (#071827) and `--p-surface-50` (#f1f6fa); the
        post-load source is resources/js/lib/theme.ts, which reads those tokens
        instead of repeating these hexes. Change the palette, change all three. --}}
    <meta name="theme-color" content="#071827" />

    {{-- Internal staff surface. The title is server-rendered because
        Inertia SSR is off: a non-JS crawler (and every link preview) would
        otherwise read the framework default. `noindex` is the audience
        decision, and it is the one meta tag that must survive without JS. --}}
    <title inertia>AquaShield Operations</title>
    <meta name="robots" content="noindex, nofollow, noarchive" />

    {{-- Vite's dev client looks up this meta tag and copies the nonce onto
        every `<style>` tag it injects for imported CSS. Without it the CSP
        blocks all dev styles; the production build does not inject tags. --}}
    <meta property="csp-nonce" @cspNonce />

    @fonts

    {{-- Theme FOUC-killer: `.dark` on <html> BEFORE first paint. The key
        `app:theme` is owned by `resources/js/lib/theme.ts` (`useTheme`), the
        single source of persistence. Dark-first project policy: no stored
        preference = dark. --}}
    <script @cspNonce>
        (function () {
            try {
                var stored = localStorage.getItem('app:theme');
                var theme = stored || 'dark';
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
                document.documentElement.style.colorScheme = theme;
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) {
                    meta.setAttribute('content', theme === 'dark' ? '#071827' : '#f1f6fa');
                }
            } catch (e) {
                /* localStorage blocked: leave the document as-is */
            }
        })();
    </script>

    {{-- The React Refresh preamble MUST precede the entry: without it the dev
        bundle dies with "@vitejs/plugin-react can't detect preamble" and the
        Inertia root renders empty. It is a no-op on a production build. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
{{--
    The body is the page canvas behind every surface, so it has to follow the
    theme. It used to be `bg-aq-void` unconditionally: the transparent
    scrollbar track then showed that navy through the gutter in light mode,
    and on any viewport wider than the shell's `max-w-384` the navy showed as
    a band on both sides of the centred app.

    The text colour stays `text-aq-ink` in both modes on purpose. The three
    surfaces are: the app shell (paints `bg-surface-50` / `dark:bg-surface-950`
    and its own text colour), the marketing welcome page (`bg-aq-void`) and the
    auth shell (`bg-aq-void`, always dark). Only the last one shows body text,
    and it needs the light ink on navy.

    First paint is unchanged: the script above adds `.dark` before this
    stylesheet resolves, and dark-first means an unvisited browser still gets
    the navy canvas.
--}}
<body class="bg-surface-50 text-aq-ink dark:bg-aq-void h-full font-sans antialiased">
    @inertia
</body>
</html>
