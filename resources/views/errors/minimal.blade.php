<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>@yield('title')</title>

    {{-- Themed error shell.

         This used to be Laravel's publishable page: five hundred lines of
         literal slate hexes that rendered a white sheet on a dark-first app.
         Every class below resolves through the tokens in
         resources/css/globals.css instead, and `app.css` already scans
         resources/**/*.blade.php, so an error page cannot drift from the app
         it belongs to.

         The build check is load-bearing: this view is what renders WHEN
         something is broken, and `@vite` throws on a missing manifest. Without
         the guard, a failed deploy turns a 500 into a 500 whose error page also
         500s. With it, the page degrades to unstyled but readable markup. --}}
    @if (is_file(public_path('build/manifest.json')))
        @vite('resources/css/app.css')
    @endif
</head>
<body class="bg-surface-950 text-surface-100 flex min-h-screen items-center justify-center p-6 font-sans antialiased">
    <main class="w-full max-w-md text-center">
        <p class="text-aq-accent text-7xl font-bold tracking-tight tabular-nums">@yield('code')</p>

        <p class="text-surface-300 mt-4 text-base">@yield('message')</p>

        <a
            href="{{ url('/') }}"
            class="border-aq-line text-aq-ink hover:border-aq-line-strong hover:bg-aq-elevated/40 focus-visible:outline-aq-accent mt-8 inline-flex min-h-11 items-center rounded-lg border px-4 text-sm font-semibold transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 motion-reduce:transition-none"
        >
            {{ __('Back to home') }}
        </a>
    </main>
</body>
</html>
