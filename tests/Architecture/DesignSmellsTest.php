<?php

/*
|--------------------------------------------------------------------------
| Design smells — architecture tests
|--------------------------------------------------------------------------
|
| Two rules that are decidable from source, so they are tests rather than review:
|
| 1. DUMB PASS-THROUGH (Henney, "Two Dumb Rules of Refactoring", rule 1).
|    A method whose whole body is `return $other->method($itsOwnArgs);` forwards
|    the caller's arguments unchanged and adds nothing. The interface costs as
|    much as the implementation — Ousterhout's "shallow module", red flag
|    "pass-through variables" in "A Philosophy of Software Design". Either it is
|    a real abstraction worth naming, or it is deleted and the caller calls the
|    target directly.
|
|    Deliberately NARROW. A wrapper that transforms an argument
|    (`delete($this->normalizeObjectKey($path))`) is doing work and is NOT flagged;
|    flagging it would be a false positive on correct code. The wide version of
|    this check flagged 4 real methods in this repo before being narrowed to 0.
|
| 2. NO ORPHAN MODULE CODE (YAGNI: Fowler's "presumptive feature").
|    A class a module declares, that nothing else in the tree references, is a
|    presumptive feature: it was built, it is paid for in construction and in
|    delay, and — in Jeremy Miller's words — "any extensibility point that's never
|    used isn't just wasted effort, it's likely to also get in your way as well".
|
|    Scoped to `src/Modules` on purpose. `src/Shared` legitimately holds adapters
|    awaiting their first consumer, and its own rule says so; asserting otherwise
|    would be a test that fights the policy it is meant to enforce.
|
| Both skip rather than pass vacuously. ModuleComplexityTest asserts the vacuous
| state out loud so "skipped" never reads as "green".
|
*/

declare(strict_types=1);

$projectPath = dirname(__DIR__, 2);
$modulesPath = "$projectPath/src/Modules";

/** @return list<string> */
function phpFilesIn(string $dir, string $projectPath): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $root = str_replace('\\', '/', $projectPath);
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($out);

    return $out;
}

function paramNames(string $signature): array
{
    preg_match('/function\s+\w+\s*\((.*)\)/s', $signature, $m);

    if (! isset($m[1])) {
        return [];
    }

    $params = [];
    // Parameter lists here never nest, so a flat split is safe and a nested
    // default array simply never matches a forwarded argument list.
    foreach (explode(',', $m[1]) as $piece) {
        if (preg_match('/\$(\w+)/', $piece, $p) === 1) {
            $params[] = $p[1];
        }
    }

    return $params;
}

/**
 * @return list<array{file: string, line: int, method: string, target: string}>
 */
function dumbPassThroughs(array $files, string $projectPath): array
{
    $hits = [];

    foreach ($files as $rel) {
        $lines = preg_split('/\R/', (string) file_get_contents("$projectPath/$rel")) ?: [];

        for ($i = 0; $i <= count($lines) - 4; $i++) {
            $sig = trim($lines[$i] ?? '');
            $open = trim($lines[$i + 1] ?? '');
            $body = trim($lines[$i + 2] ?? '');
            $close = trim($lines[$i + 3] ?? '');

            if (preg_match('/^(?:public|protected)\s+function\s+(\w+)\s*\((.*)\)/s', $sig, $m) !== 1) {
                continue;
            }

            if ($open !== '{' || $close !== '}') {
                continue;
            }

            if (preg_match('/^return\s+\$this->\w+->(\w+)\s*\((.*)\);$/', $body, $fwd) !== 1) {
                continue;
            }

            // The narrowing: the forwarded arguments must be EXACTLY the caller's own
            // parameters, in order. Anything else is work, and work is not dumb.
            // Both sides are normalised to bare names — `$key` and `key` are the same
            // parameter, and comparing them raw silently matches nothing.
            $forwarded = array_map(
                fn (string $a) => ltrim(trim($a), '$'),
                array_values(array_filter(array_map('trim', explode(',', $fwd[2])), fn ($a) => $a !== '')),
            );

            if ($forwarded !== paramNames($sig)) {
                continue;
            }

            $hits[] = ['file' => $rel, 'line' => $i + 1, 'method' => $m[1], 'target' => $fwd[1]];
        }
    }

    return $hits;
}

$moduleNames = collect(is_dir($modulesPath) ? scandir($modulesPath) : [])
    ->reject(fn ($e) => in_array($e, ['.', '..', '.gitkeep', 'README.md'], true))
    ->filter(fn ($e) => is_dir("$modulesPath/$e"))
    ->values();

$modulePhp = $moduleNames->flatMap(fn ($m) => phpFilesIn("$modulesPath/$m", $projectPath))->values()->all();
$firstPartyPhp = phpFilesIn("$projectPath/src", $projectPath);

/* ------------------------------------------------------------- guard rails */

it('has first-party sources to analyse', function () use ($firstPartyPhp) {
    expect($firstPartyPhp)->not->toBe([], 'No PHP found under src/ — the smell checks below are vacuous.');
});

/* ------------------------------------------------- 1. dumb pass-through */

it('has no dumb pass-through methods', function () use ($firstPartyPhp, $projectPath) {
    $hits = dumbPassThroughs($firstPartyPhp, $projectPath);

    expect($hits)->toBe([], "A method that only forwards its own arguments is a shallow module — call the target directly, or make the wrapper earn its place by doing something:\n  ".implode(
        "\n  ",
        array_map(fn ($h) => "{$h['file']}:{$h['line']}  {$h['method']}() -> {$h['target']}()", $hits),
    ));
});

/* --------------------------------------------- 2. no orphan module code */

it('declares no class that nothing else references', function () use ($modulePhp, $firstPartyPhp, $projectPath) {
    $references = '';

    foreach ($firstPartyPhp as $rel) {
        $references .= (string) file_get_contents("$projectPath/$rel");
    }

    // Wiring lives outside src/ — a module ServiceProvider is referenced ONLY from
    // bootstrap/providers.php. Scanning src/ alone flagged every generated module's
    // provider as an orphan, which is the rule being wrong rather than the code.
    foreach (['bootstrap/providers.php', 'routes/web.php', 'routes/api.php', 'config'] as $extra) {
        $path = "$projectPath/$extra";

        if (is_file($path)) {
            $references .= (string) file_get_contents($path);
        } elseif (is_dir($path)) {
            foreach (phpFilesIn($path, $projectPath) as $rel) {
                $references .= (string) file_get_contents("$projectPath/$rel");
            }
        }
    }

    $orphans = [];

    foreach ($modulePhp as $rel) {
        $src = (string) file_get_contents("$projectPath/$rel");

        if (preg_match('/^final\s+(?:readonly\s+)?class\s+(\w+)/m', $src, $m) !== 1) {
            continue;
        }

        $class = $m[1];

        // Count references outside the declaring file. Zero means the class exists
        // for nobody: a presumptive feature that cost build time and delay.
        $uses = preg_match_all('/\b'.preg_quote($class, '/').'\b/', $references) - 1; // minus its own declaration

        if ($uses <= 0) {
            $orphans[] = "{$rel}  ->  class {$class}";
        }
    }

    expect($orphans)->toBe([], "A class nothing references is a presumptive feature (Fowler) — delete it, or give it a consumer today. YAGNI, not a plan:\n  ".implode("\n  ", $orphans));
})->skip(fn () => $modulePhp === [], 'src/Modules is empty — nothing to orphan yet.');

/* ------------------------------------------------- 3. no speculative folders */

it('ships no speculative Contracts folder in a module', function () use ($moduleNames, $modulesPath) {
    $found = [];

    foreach ($moduleNames as $module) {
        foreach (['Contracts', 'Interfaces', 'Abstracts', 'Base', 'Concerns', 'Traits'] as $folder) {
            $path = "$modulesPath/$module/$folder";

            if (is_dir($path)) {
                $found[] = "{$module}/{$folder}";
            }
        }
    }

    expect($found)->toBe([], "A module with a generic interface/abstract folder is speculative by construction — a port belongs in Domain/Repositories or Application/, named for the thing it abstracts, and only with ≥2 implementations or a test that substitutes it:\n  ".implode("\n  ", $found));
})->skip(fn () => $moduleNames->isEmpty(), 'src/Modules is empty.');
