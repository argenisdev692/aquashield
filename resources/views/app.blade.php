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
        `--color-aq-void` (#081829) and `--p-surface-50` (#f1f6fa); the
        post-load source is resources/js/lib/theme.ts, which reads those tokens
        instead of repeating these hexes. Change the palette, change all three. --}}
    <meta name="theme-color" content="#081829" />

    {{-- Internal staff surface. The title is server-rendered because
        Inertia SSR is off: a non-JS crawler (and every link preview) would
        otherwise read the framework default. `noindex` is the audience
        decision, and it is the one meta tag that must survive without JS. --}}
    <title inertia>AquaShield Operations</title>
    <meta name="robots" content="noindex, nofollow, noarchive" />

    @fonts

    {{-- Theme FOUC-killer: `.dark` on <html> BEFORE first paint. The key
        `app:theme` is owned by the VueUse `useColorMode` store (single source
        of persistence). Dark-first project policy: no stored preference = dark. --}}
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
                    meta.setAttribute('content', theme === 'dark' ? '#081829' : '#f1f6fa');
                }
            } catch (e) {
                /* localStorage blocked, fall through to default light */
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="bg-surface-50 text-surface-700 dark:bg-surface-950 dark:text-surface-100 h-full font-sans antialiased">
    @inertia
</body>
</html>
