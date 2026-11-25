<?php

/*
|--------------------------------------------------------------------------
| Frontend boundaries — architecture tests
|--------------------------------------------------------------------------
|
| The five invariants in .opencode/skills/frontend/SKILL-ARCHITECTURE.md §4,
| plus the greppable rules from frontend/SKILL.md (tokens, zero any) and the
| generated-declaration scan that keeps the zero-any rule honest.
|
| Pest's arch() plugin reads the PHP class graph, so it cannot help here: these
| are boundaries between .tsx files. The checks are source greps — which is
| honest, not a compromise. A rule that cannot be read from the source is a
| comment; these can be read.
|
| Every test skips when its tree is empty rather than passing vacuously, and
| ModuleComplexityTest asserts the vacuous state out loud so "skipped" never
| reads as "green".
|
| A note on patterns ending in a literal slash: `['"]@\//` is `['"]@\/` plus the
| closing delimiter. Writing `['"]@/` silently makes the slash the delimiter and
| the regex matches any import from '@ — which is how this file first reported 20
| false positives on files that only import '@/components/ui/...'.
|
*/

declare(strict_types=1);

$projectPath = dirname(__DIR__, 2);
$pagesPath = "$projectPath/resources/js/Pages";
$componentsPath = "$projectPath/resources/js/Components";
$layoutsPath = "$projectPath/resources/js/Layouts";
$modulesPath = "$projectPath/resources/js/Modules";

/** Repository-relative, forward-slashed list of .tsx files under a directory. */
function tsxFilesIn(string $dir, string $projectPath): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $root = str_replace('\\', '/', $projectPath);
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.tsx')) {
            $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($out);

    return $out;
}

/** Repository-relative, forward-slashed list of .ts files under a directory (declarations excluded). */
function tsFilesIn(string $dir, string $projectPath): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $root = str_replace('\\', '/', $projectPath);
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        $name = $file->getFilename();

        if ($file->isFile() && str_ends_with($name, '.ts') && ! str_ends_with($name, '.d.ts')) {
            $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($out);

    return $out;
}

function tsxSources(array $files, string $projectPath): array
{
    return array_map(fn ($rel) => (string) file_get_contents("$projectPath/$rel"), $files);
}

function tsxFilesMatching(array $files, string $projectPath, string $pattern): array
{
    $hits = [];

    foreach ($files as $rel) {
        if (preg_match($pattern, (string) file_get_contents("$projectPath/$rel")) === 1) {
            $hits[] = $rel;
        }
    }

    return $hits;
}

/** Module hooks — the server-state layer. One `hooks/` directory per context module. */
function moduleHooksIn(string $dir, string $projectPath): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $root = str_replace('\\', '/', $projectPath);
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if (
            $file->isFile()
            && str_ends_with($file->getFilename(), '.ts')
            && str_contains(str_replace('\\', '/', $file->getPathname()), '/hooks/')
        ) {
            $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($out);

    return $out;
}

$appTsx = array_merge(
    tsxFilesIn($pagesPath, $projectPath),
    tsxFilesIn($componentsPath, $projectPath),
    tsxFilesIn($layoutsPath, $projectPath),
);
$moduleHooks = moduleHooksIn($modulesPath, $projectPath);
$frontendTs = array_merge(
    tsFilesIn("$projectPath/resources/js/lib", $projectPath),
    tsFilesIn($modulesPath, $projectPath),
);

/* --------------------------------------------------------------- guard rails */

it('finds frontend sources to test', function () use ($appTsx, $moduleHooks, $frontendTs) {
    // If the globs break, every assertion below passes vacuously.
    expect($appTsx)->not->toBe([], 'No .tsx files under resources/js — the boundary checks are vacuous.');
    expect($moduleHooks)->not->toBe([], 'No module hooks under resources/js/Modules — the boundary checks are vacuous.');
    expect($frontendTs)->not->toBe([], 'No .ts files under resources/js/lib or Modules — the zero-any check is vacuous.');
});

/* ---------------------------------------------------------------- invariant 1 */

it('network I/O lives in module hooks, not in components', function () use ($appTsx, $projectPath) {
    // Invariant 1: network I/O lives in Modules/{context}/hooks/. A page declares
    // what it shows; a module hook knows how to get it. `requestJson` is included
    // because it is the repo's own transport: banning `fetch(` alone let a page
    // call `requestJson('/backups/run')` and stay green.
    $hits = tsxFilesMatching(
        $appTsx,
        $projectPath,
        '~(?:^|[^\w$.])(fetch\s*\(|requestJson\s*\(|new\s+XMLHttpRequest|new\s+WebSocket|\baxios\b)~',
    );

    expect($hits)->toBe([], "Network I/O outside a module hook — it belongs in resources/js/Modules/{context}/hooks/:\n  ".implode("\n  ", $hits));
});

it('limits network I/O in lib to the declared transport helpers', function () use ($projectPath) {
    /*
     * Invariant 1 has one blind spot the tsx globs cannot see: a helper in
     * `lib/` that performs the request a page asked for. That is how a page
     * keeps a clean import list while still owning a write. The list below is
     * the whole exception, deliberately: `http.ts` *is* the transport, and
     * `crud-table.ts` carries the bulk delete/restore that five list screens
     * share (Rule of Three — five hook copies would be worse).
     */
    $lib = tsFilesIn("$projectPath/resources/js/lib", $projectPath);

    $hits = tsxFilesMatching(
        $lib,
        $projectPath,
        '~(?:^|[^\w$.])(fetch\s*\(|requestJson\s*\(|new\s+XMLHttpRequest|new\s+WebSocket|\baxios\b)~',
    );

    expect($hits)->toBe(
        ['resources/js/lib/crud-table.ts', 'resources/js/lib/http.ts'],
        "Network I/O in resources/js/lib must be one of the declared transport helpers:\n  ".implode("\n  ", $hits),
    );
});

/* ------------------------------------------- invariant 2: state lives in module hooks */

it('server state lives in module hooks', function () use ($appTsx, $projectPath) {
    // Invariant 2: TanStack Query / TanStack Table are wired through the module
    // hooks (plus the app shell for the provider). Pages and components never
    // touch a query cache or a table instance directly — the hook owns the key,
    // the fetcher and the cache policy.
    $hits = tsxFilesMatching(
        $appTsx,
        $projectPath,
        '~from\s+[\'"]@tanstack/(?:react-query|react-table)[\'"]~',
    );

    expect($hits)->toBe([], "Server state belongs in Modules/{context}/hooks/ — pages and components never reach into @tanstack directly:\n  ".implode("\n  ", $hits));
});

it('documents the only lib modules that know about TanStack', function () use ($projectPath) {
    /*
     * The same blind spot as invariant 1: `lib/` is outside the tsx glob, so a
     * helper can import a query cache or a table instance and stay green.
     * `data-table.ts` is the shared table adapter (the kit cannot own the
     * engine and still be importable by five pages), and `auth.ts` clears the
     * cache on logout so the next actor cannot read the previous one's rows.
     * A third entry is a design decision, not a convenience — make it here.
     */
    $lib = tsFilesIn("$projectPath/resources/js/lib", $projectPath);

    $hits = tsxFilesMatching($lib, $projectPath, '~from\s+[\'"]@tanstack/(?:react-query|react-table)[\'"]~');

    expect($hits)->toBe(
        ['resources/js/lib/auth.ts', 'resources/js/lib/data-table.ts'],
        "lib modules importing @tanstack must be documented here:\n  ".implode("\n  ", $hits),
    );
});

/* ------------------------------------ invariant 5: server rows are not re-ordered */

it('the table kit renders server rows in the order received', function () use ($projectPath) {
    // Invariant 5: a paginated endpoint already decided the order and the page.
    // Re-sorting or slicing `data` in the kit shows the wrong rows on page 2 and
    // makes the export disagree with the screen. The check is deliberately
    // scoped to the kit, where every list's server rows pass through.
    $file = 'resources/js/Components/DataTable/DataTableKit.tsx';
    $path = "$projectPath/$file";

    expect(is_file($path))->toBeTrue("Missing $file — invariant 5 cannot be verified.");

    $source = (string) file_get_contents($path);

    expect($source)->not->toMatch(
        '~\.(?:sort|slice|reverse)\s*\(~',
        'DataTableKit must not sort, slice or reverse the server rows it receives.',
    );
});

it('the TanStack table adapter stays server-first', function () use ($projectPath) {
    // TanStack Table v9 owns the table model, so the manual flags are the
    // contract that stops it from sorting or paginating the loaded page. A
    // future edit that registers a client row model would silently reorder the
    // screen while the URL and export still describe the server's order.
    $file = 'resources/js/lib/data-table.ts';
    $path = "$projectPath/$file";

    expect(is_file($path))->toBeTrue("Missing $file — the table engine contract cannot be verified.");

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('manualPagination: true')
        ->toContain('manualSorting: true')
        ->toContain('rowCount: total')
        ->not->toContain('sortedRowModel')
        ->not->toContain('paginatedRowModel')
        ->not->toContain('filteredRowModel');
});

/* -------------------------------------------------------------- token discipline */

it('no hardcoded colour in tsx sources', function () use ($appTsx, $projectPath) {
    // The token-first rule, made greppable. A hex literal in a component is always
    // a bypass of globals.css and the token bridge — but so is a colour function
    // inside an arbitrary value. A call site that needs an elevation, a glow or a
    // mix gets a token in globals.css, not a bracket here.
    $hits = tsxFilesMatching(
        $appTsx,
        $projectPath,
        '~(?:#[0-9a-fA-F]{3,8}\b|-\[#[0-9a-fA-F]|-\[[^\]\n]*(?:rgba?\(|hsla?\(|oklch?\(|oklab\(|lab\(|lch\())~',
    );

    expect($hits)->toBe([], "Hardcoded colour — use var(--token) or a token class:\n  ".implode("\n  ", $hits));
});

it('no any, as any or ts-ignore in tsx sources', function () use ($appTsx, $projectPath) {
    // The hardest frontend rule in this repo and the cheapest to grep.
    $hits = [];
    foreach ($appTsx as $rel) {
        if (preg_match('~(?:\bas\s+any\b|:\s*any\b|<any>|@ts-ignore|@ts-expect-error)~', (string) file_get_contents("$projectPath/$rel"))) {
            $hits[] = $rel;
        }
    }

    expect($hits)->toBe([], "`any` or a ts-ignore — use `unknown` plus a narrowing guard:\n  ".implode("\n  ", $hits));
});

it('no any, as any or ts-ignore in ts sources', function () use ($frontendTs, $projectPath) {
    // The .tsx check above stops at the component tree; the form engine and the
    // module hooks are .ts, and that is exactly where an `any` used to hide.
    $hits = [];
    foreach ($frontendTs as $rel) {
        if (preg_match('~(?:\bas\s+any\b|:\s*any\b|<any>|@ts-ignore|@ts-expect-error)~', (string) file_get_contents("$projectPath/$rel"))) {
            $hits[] = $rel;
        }
    }

    expect($hits)->toBe([], "`any` or a ts-ignore in a .ts source — use `unknown` plus a narrowing guard:\n  ".implode("\n  ", $hits));
});

it('no any in generated declaration files', function () use ($projectPath) {
    // `tsFilesIn()` skips `.d.ts`, and the .tsx scan never sees the generated
    // tree — so a backend `mixed` becomes a shipped `any` with every gate green.
    // This is the one place the zero-any rule has to look at generator output.
    $files = glob("$projectPath/resources/js/generated/*.d.ts") ?: [];

    expect($files)->not->toBe([], 'No generated declaration files — the zero-any check is vacuous.');

    $hits = [];

    foreach ($files as $path) {
        if (preg_match('~\bany\b~', (string) file_get_contents($path)) === 1) {
            $hits[] = basename($path);
        }
    }

    expect($hits)->toBe([], "`any` in a generated declaration — fix the Data class, then regenerate:\n  ".implode("\n  ", $hits));
});

/* --------------------------------------------------- broader colour discipline */

// A raw Tailwind palette class resolves to a literal colour in the build, so it
// bypasses globals.css exactly like a hex literal does. The hex check above cannot
// see `text-red-600`, which is why six Auth pages shipped one unnoticed while
// "no hardcoded colour" was reported as satisfied. A call site that needs an
// elevation, a glow or a mix gets a token in globals.css, not a bracket here.
const TAILWIND_PALETTE_CLASS = '~\b(?:bg|text|border|ring|shadow|from|via|to|fill|stroke|outline|decoration|divide|accent|caret|placeholder)-(?:(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(?:50|100|200|300|400|500|600|700|800|900|950)|white|black)\b~';

it('no raw tailwind palette colour in tsx sources', function () use ($appTsx, $projectPath) {
    $hits = [];

    foreach ($appTsx as $rel) {
        $source = (string) file_get_contents("$projectPath/$rel");

        if (preg_match_all(TAILWIND_PALETTE_CLASS, $source, $matches) > 0) {
            $hits[] = $rel.' -> '.implode(', ', array_values(array_unique($matches[0])));
        }
    }

    expect($hits)->toBe([], "Raw palette colour — it compiles to a literal, not a token. Use the aq-* scale or a var(--token):\n  ".implode("\n  ", $hits));
});

/* --------------------------------------------------- motion that announces itself */

it('every interactive state carries a transition', function () use ($appTsx, $projectPath) {
    // A hover or focus-visible that changes colour with no duration does not
    // read as restraint — it reads as a rendering glitch. Scoped to the SAME
    // class attribute so an unrelated `transition` elsewhere in the file
    // cannot mask a snapping state. In React the class lives in `className`.
    $hits = [];

    foreach ($appTsx as $rel) {
        $source = (string) file_get_contents("$projectPath/$rel");
        preg_match_all('/className="([^"]*)"/', $source, $attributes);

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
it('every internal anchor link resolves to an id', function () use ($appTsx, $projectPath) {
    $ids = [];
    $links = [];

    foreach ($appTsx as $rel) {
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
