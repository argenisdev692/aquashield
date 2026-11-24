<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Frontend route links — architecture test
|--------------------------------------------------------------------------
|
| A dead internal link is invisible to every other check: TypeScript accepts
| any string, Inertia only fails when the user clicks. This is the defect class
| that shipped `href="/activity-log"` while the registered route was
| `/activity`.
|
| The scan is deliberately narrow. It checks literal `href="/…"` attributes in
| the React tree against the GET routes the application actually registered.
| Template literals (`href={`/users/${uuid}`}`) and query/fragment suffixes are
| out of scope: the path is resolved against the route table, not guessed from
| source.
|
*/

/**
 * Convert a registered URI (`users/{user:uuid}`, `telescope/{view?}`) into a
 * matcher. Parameters are one segment; an optional trailing segment may be
 * absent entirely.
 */
function routeMatcher(string $uri): string
{
    $pattern = '';

    foreach (explode('/', $uri) as $segment) {
        if ($segment === '') {
            continue;
        }

        if (preg_match('/^\{[^}]+\?\}$/', $segment) === 1) {
            $pattern .= '(?:/'.preg_quote(substr($segment, 1, -2), '~').')?';

            continue;
        }

        if (preg_match('/^\{[^}]+\}$/', $segment) === 1) {
            $pattern .= '/[^/]+';

            continue;
        }

        $pattern .= '/'.preg_quote($segment, '~');
    }

    return '~^'.($pattern === '' ? '/' : $pattern).'$~';
}

it('every literal internal link resolves to a registered route', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $router = app(Router::class);

    $matchers = [];

    foreach ($router->getRoutes()->getRoutes() as $route) {
        if (in_array('GET', $route->methods(), true)) {
            $matchers[] = routeMatcher($route->uri());
        }
    }

    $files = [];

    foreach (['Pages', 'Components', 'Layouts'] as $directory) {
        $root = "$projectPath/resources/js/$directory";

        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.tsx')) {
                $files[] = $file->getPathname();
            }
        }
    }

    expect($files)->not->toBe([], 'No .tsx files found — the link check is vacuous.');
    expect($matchers)->not->toBe([], 'No GET routes registered — the link check is vacuous.');

    $dead = [];

    foreach ($files as $path) {
        $matched = preg_match_all('/\bhref="(\/[^"#?]*)/', (string) file_get_contents($path), $matches);

        if ($matched === false || $matched === 0) {
            continue;
        }

        foreach (array_unique($matches[1]) as $href) {
            $candidate = $href === '' ? '/' : $href;
            $resolved = false;

            foreach ($matchers as $matcher) {
                if (preg_match($matcher, $candidate) === 1) {
                    $resolved = true;

                    break;
                }
            }

            if (! $resolved) {
                $relative = str_replace('\\', '/', substr($path, strlen($projectPath) + 1));
                $dead[] = $relative.'  ->  '.$href;
            }
        }
    }

    expect($dead)->toBe([], "Internal link with no registered GET route:\n  ".implode("\n  ", $dead));
});
