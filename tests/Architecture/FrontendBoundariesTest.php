<?php

/*
|--------------------------------------------------------------------------
| Frontend boundaries — architecture tests
|--------------------------------------------------------------------------
|
| The four invariants in .opencode/skills/frontend/SKILL-ARCHITECTURE.md §4,
| plus the two greppable rules from frontend/SKILL.md (tokens, zero any).
|
| Pest's arch() plugin reads the PHP class graph, so it cannot help here: these
| are boundaries between .vue files. The checks are source greps — which is
| honest, not a compromise. A rule that cannot be read from the source is a
| comment; these four can be read.
|
| Every test skips when its tree is empty rather than passing vacuously, and
| ModuleComplexityTest asserts the vacuous state out loud so "skipped" never
| reads as "green".
|
| A note on patterns ending in a literal slash: `['"]@\//` is `['"]@\/` plus the
| closing delimiter. Writing `['"]@/` silently makes the slash the delimiter and
| the regex matches any import from '@ — which is how this file first reported 20
| false positives on files that only import '@primevue/icons/...'.
|
*/

declare(strict_types=1);

$projectPath = dirname(__DIR__, 2);
$voltPath = "$projectPath/src/volt";
$pagesPath = "$projectPath/resources/js/Pages";
$componentsPath = "$projectPath/resources/js/Components";
$layoutsPath = "$projectPath/resources/js/Layouts";

/** Repository-relative, forward-slashed list of .vue files under a directory. */
function vueFilesIn(string $dir, string $projectPath): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $root = str_replace('\\', '/', $projectPath);
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.vue')) {
            $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($out);

    return $out;
}

function vueSources(array $files, string $projectPath): array
{
    return array_map(fn ($rel) => (string) file_get_contents("$projectPath/$rel"), $files);
}

function vueFilesMatching(array $files, string $projectPath, string $pattern): array
{
    $hits = [];

    foreach ($files as $rel) {
        if (preg_match($pattern, (string) file_get_contents("$projectPath/$rel")) === 1) {
            $hits[] = $rel;
        }
    }

    return $hits;
}

/**
 * PrimeVue entry points that are NOT components and therefore have no Volt
 * wrapper: services, composables, the config object, the directive, and the two
 * subpaths Volt does not own (Column, icons). Everything else is a component the
 * app must reach through @/volt/*.
 */
const PRIMEVUE_NON_COMPONENT = [
    'config',
    'usetoast',
    'useconfirm',
    'toastservice',
    'confirmationservice',
    'tooltip',
    'column',
    'icons',
    'api',
    'utils',
];

$appVue = array_merge(
    vueFilesIn($pagesPath, $projectPath),
    vueFilesIn($componentsPath, $projectPath),
    vueFilesIn($layoutsPath, $projectPath),
);
$voltVue = vueFilesIn($voltPath, $projectPath);

/* --------------------------------------------------------------- guard rails */

it('finds frontend sources to test', function () use ($appVue, $voltVue) {
    // If the globs break, every assertion below passes vacuously.
    expect($appVue)->not->toBe([], 'No .vue files under resources/js — the boundary checks are vacuous.');
    expect($voltVue)->not->toBe([], 'No .vue files in src/volt — the Volt boundary checks are vacuous.');
});

/* ---------------------------------------------------------------- invariant 1 */

it('pages and components never call the network directly', function () use ($appVue, $projectPath) {
    // Invariant 1: network I/O lives in Modules/{ctx}/composables/. A page declares
    // what it shows; a composable knows how to get it.
    $hits = vueFilesMatching(
        $appVue,
        $projectPath,
        '~(?:^|[^\w$.])(fetch\s*\(|new\s+XMLHttpRequest|new\s+WebSocket|\baxios\b)~',
    );

    expect($hits)->toBe([], "Network I/O outside a composable — it belongs in src/Modules/{ctx}/composables/:\n  ".implode("\n  ", $hits));
});

/* ---------------------------------------------------------------- invariant 2 */

it('vue files outside src/volt do not import primevue components directly', function () use ($appVue, $projectPath) {
    // Invariant 2: a PrimeVue COMPONENT is reached through @/volt/*. Services,
    // composables and Column have no Volt wrapper and stay allowed — blocking
    // `useToast` would mean no toasts at all.
    $offenders = [];

    foreach ($appVue as $rel) {
        preg_match_all(
            '~from\s+[\'"](?:primevue|@primevue)/([A-Za-z0-9_/-]+)[\'"]~',
            (string) file_get_contents("$projectPath/$rel"),
            $matches,
        );

        foreach ($matches[1] as $entry) {
            $head = strtolower((string) strtok($entry, '/'));

            if (! in_array($head, PRIMEVUE_NON_COMPONENT, true)) {
                $offenders[] = "$rel  ->  primevue/$entry";
            }
        }
    }

    expect($offenders)->toBe([], "Import the Volt wrapper instead of the PrimeVue component:\n  ".implode("\n  ", $offenders));
});

/* ---------------------------------------------------------------- invariant 3 */

it('src/volt never imports from the application', function () use ($voltVue, $projectPath) {
    // Invariant 3: the primitives are code-ownership copies that sit BELOW the app.
    // An @/ import there is a captured dependency the next `volt-vue add` will not
    // reproduce, so the upgrade silently drops it.
    $hits = vueFilesMatching($voltVue, $projectPath, '~from\s+[\'"]@/~');

    expect($hits)->toBe([], "src/volt must not import from resources/js — it is a layer below the app:\n  ".implode("\n  ", $hits));
});

/* ---------------------------------------------------------------- invariant 4 */

it('no call-site pt override outside src/volt', function () use ($appVue, $projectPath) {
    // Invariant 4: customise a Volt primitive by editing its own pt pass-through
    // config, not by passing :pt at the usage site. Inline pt is invisible to a Volt
    // upgrade and silently defeats the token layer.
    $hits = vueFilesMatching($appVue, $projectPath, '~(?:^|\s):pt=~');

    expect($hits)->toBe([], "Inline :pt at the call site — edit the component's own pt config in src/volt/:\n  ".implode("\n  ", $hits));
});

/* -------------------------------------------------------------- token discipline */

it('no hardcoded colour in vue sources', function () use ($appVue, $projectPath) {
    // The token-first rule, made greppable. A hex literal in a .vue file is always a
    // bypass of globals.css and the PrimeUI bridge — but so is a colour function
    // inside an arbitrary value. `hover:shadow-[0_20px_25px_-5px_rgb(0_0_0/0.10)]`
    // shipped through the hex-only version of this check and painted a literal
    // black over the navy card, because the guard could not see it. A call site
    // that needs an elevation, a glow or a mix gets a token in globals.css, not a
    // bracket here.
    $hits = vueFilesMatching(
        $appVue,
        $projectPath,
        '~(?:#[0-9a-fA-F]{3,8}\b|-\[#[0-9a-fA-F]|-\[[^\]\n]*(?:rgba?\(|hsla?\(|oklch?\(|oklab\(|lab\(|lch\(|color-mix\())~',
    );

    expect($hits)->toBe([], "Hardcoded colour — use var(--token) or a PrimeUI bridge class:\n  ".implode("\n  ", $hits));
});

it('no any, as any or ts-ignore in vue sources', function () use ($appVue, $projectPath) {
    // The hardest frontend rule in this repo and the cheapest to grep.
    $hits = [];
    foreach ($appVue as $rel) {
        if (preg_match('~(?:\bas\s+any\b|:\s*any\b|<any>|@ts-ignore|@ts-expect-error)~', (string) file_get_contents("$projectPath/$rel"))) {
            $hits[] = $rel;
        }
    }

    expect($hits)->toBe([], "`any` or a ts-ignore — use `unknown` plus a narrowing guard:\n  ".implode("\n  ", $hits));
});

/* --------------------------------------------------- broader colour discipline */

// A raw Tailwind palette class resolves to a literal colour in the build, so it
// bypasses globals.css exactly like a hex literal does. The hex check above
// cannot see `text-red-600`, which is why six Auth pages shipped one unnoticed
// while "no hardcoded colour" was reported as satisfied.
const TAILWIND_PALETTE_CLASS = '~\b(?:bg|text|border|ring|shadow|from|via|to|fill|stroke|outline|decoration|divide|accent|caret|placeholder)-(?:(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(?:50|100|200|300|400|500|600|700|800|900|950)|white|black)\b~';

it('no raw tailwind palette colour in vue sources', function () use ($appVue, $projectPath) {
    $hits = [];

    foreach ($appVue as $rel) {
        $source = (string) file_get_contents("$projectPath/$rel");

        if (preg_match_all(TAILWIND_PALETTE_CLASS, $source, $matches) > 0) {
            $hits[] = $rel.' -> '.implode(', ', array_values(array_unique($matches[0])));
        }
    }

    expect($hits)->toBe([], "Raw palette colour — it compiles to a literal, not a token. Use the aq-* scale or a var(--token):\n  ".implode("\n  ", $hits));
});

/* --------------------------------------------------- motion that announces itself */

it('every interactive state carries a transition', function () use ($appVue, $projectPath) {
    // A hover or focus-visible that changes colour with no duration does not
    // read as restraint — it reads as a rendering glitch. Scoped to the SAME
    // class attribute so an unrelated `transition` elsewhere in the file
    // cannot mask a snapping state.
    $hits = [];

    foreach ($appVue as $rel) {
        $source = (string) file_get_contents("$projectPath/$rel");
        preg_match_all('/class="([^"]*)"/', $source, $attributes);

        foreach ($attributes[1] as $classList) {
            if (preg_match('~(?:^|\s)(?:hover|focus-visible|group-hover):~', $classList) !== 1) {
                continue;
            }

            $hasTransition =
                preg_match('~(?:^|\s)transition(?:-|\[|[\s"])~', $classList) === 1
                || str_contains($classList, 'motion-reduce:');

            if (! $hasTransition) {
                $hits[] = $rel.'  ->  '.$classList;
            }
        }
    }

    expect($hits)->toBe([], "Interactive state with no transition — add a `transition-*` with a duration:\n  ".implode("\n  ", $hits));
});

/* ------------------------------------------------------- anchor integrity */

// A nav written before its sections exist is exactly how a dead link ships: the
// header renders fine and the target 404s into nothing. Matching only the
// literal `href="#x"` attribute checked 4 of 18 links, because these components
// drive navigation from arrays — `{ href: '#x' }` — which that pattern misses.
// The first version of this check therefore reported clean while checking
// almost nothing. Both forms are matched here.
it('every internal anchor link resolves to an id', function () use ($appVue, $projectPath) {
    $ids = [];
    $links = [];

    foreach ($appVue as $rel) {
        $source = (string) file_get_contents("$projectPath/$rel");

        preg_match_all('/\bid="([a-z0-9-]+)"/i', $source, $idMatches);
        preg_match_all('/href\s*[:=]\s*["\']#([a-z0-9-]+)["\']/i', $source, $hrefMatches);

        foreach ($idMatches[1] as $id) {
            $ids[$id] = true;
        }

        foreach ($hrefMatches[1] as $anchor) {
            $links[$anchor][] = $rel;
        }
    }

    $dead = [];

    foreach ($links as $anchor => $sources) {
        if (! isset($ids[$anchor])) {
            $dead[] = '#'.$anchor.'  <-  '.implode(', ', array_unique($sources));
        }
    }

    expect($dead)->toBe([], "Anchor with no matching id — either add the section or remove the link:\n  ".implode("\n  ", $dead));
});
