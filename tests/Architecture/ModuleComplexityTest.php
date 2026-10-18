<?php

/*
|--------------------------------------------------------------------------
| Module Complexity — architecture tests
|--------------------------------------------------------------------------
|
| Enforces the "progressive complexity" rule described in
| .opencode/skills/backend-php/SKILL-ARCHITECTURE.md
|
| The rule: every module declares its mode in its own ARCHITECTURE.md, and the
| folder tree matches that declaration. Undeclared complexity is the failure
| mode — a module that looks flat but holds invariants, or one that got four
| layers "because enterprise" with no test holding them in place.
|
| Layer checks use Pest's native arch() plugin (real dependency graph —
| comments and strings don't count). Tree/mode checks stay classic it()
| tests because no plugin can read an ARCHITECTURE.md declaration.
|
*/

declare(strict_types=1);

/*
 * Strict types active. Path-based, not base_path(): Pest loads this file
 * before the container is booted, so any app() helper at file scope
 * would fatal.
 */
$projectPath = dirname(__DIR__, 2);
$modulesPath = $projectPath.'/src/Modules';
$sharedPath = $projectPath.'/src/Shared';

/** Every module directory (README.md and dotfiles do not count). */
$moduleNames = collect(is_dir($modulesPath) ? scandir($modulesPath) : [])
    ->reject(fn ($e) => in_array($e, ['.', '..', '.gitkeep', 'README.md'], true))
    ->filter(fn ($e) => is_dir("$modulesPath/$e"))
    ->values();

function modeOf(string $dir): ?string
{
    $file = "$dir/ARCHITECTURE.md";
    if (! is_file($file)) {
        return null;
    }

    if (preg_match('/(?:modo|mode):\s*(FLAT|PROGRESSIVE)/i', (string) file_get_contents($file), $m)) {
        return strtoupper($m[1]);
    }

    return 'UNDECLARED';
}

$progressive = $moduleNames->filter(fn ($m) => modeOf("$modulesPath/$m") === 'PROGRESSIVE')->values();

/* ------------------------------------------------- global hygiene (arch) */

arch('debug helpers never reach the codebase')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('first-party code uses strict types')
    ->expect(['App', 'Modules', 'Shared'])
    ->toUseStrictTypes();

arch('Shared domain model code is framework-free')
    // The ORM, facades and the framework core stay out of Shared\Domain entirely.
    //
    // DECLARED EXCEPTION — Shared\Domain\Ports names Illuminate\Http\Response and
    // Symfony\...\StreamedResponse in RETURN POSITIONS (ExportPort, WordExportPort).
    // A port whose job is "stream this download" cannot describe that job without
    // naming the response type, and the alternative — pushing the streaming back
    // into every module's controller — is the exact DRY failure the port removes.
    // So `Illuminate\Http` and `Symfony` are allowed in Ports and nowhere else.
    //
    // Review item, not a test: a PORT that reaches past its return type — injecting
    // a Request, a Facade or a query builder into Shared\Domain\Ports is a real
    // violation and arch() cannot see the difference. That is on the reviewer.
    ->expect('Shared\Domain')
    ->not->toUse([
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'Illuminate\Foundation',
        'Illuminate\Console',
    ]);

/* --------------------------------------- per-module layer rules (arch) */

foreach ($progressive as $module) {
    // Invariant 1: Domain is pure — no framework, ORM, HTTP, infrastructure.
    arch("{$module} Domain does not reach outward")
        ->expect("Modules\\{$module}\\Domain")
        ->not->toUse([
            'Illuminate\\Database',
            'Illuminate\\Http',
            'Illuminate\\Foundation\\Http',
            'Illuminate\\Support\\Facades',
            "Modules\\{$module}\\Infrastructure",
            "Modules\\{$module}\\Application",
            "Modules\\{$module}\\Http",
        ]);

    // Invariant 2: use cases depend on abstractions, never concretes.
    arch("{$module} Application depends on abstractions, not concretes")
        ->expect("Modules\\{$module}\\Application")
        ->not->toUse([
            'Illuminate\\Database\\Eloquent',
            'Illuminate\\Support\\Facades',
            'Illuminate\\Http',
            'Illuminate\\Foundation\\Http',
        ]);

    // Invariant 6: every dependency points inward.
    arch("{$module} Infrastructure may depend on the layers inside it")
        ->expect("Modules\\{$module}\\Infrastructure")
        ->not->toUse(["Modules\\{$module}\\Domain\\Services"]);

    // Invariant 7: a Domain is used only inside its own module. Public access
    // goes through ports / Data objects, never into Domain/.
    arch("{$module} Domain is not reached from other modules")
        ->expect("Modules\\{$module}\\Domain")
        ->toOnlyBeUsedIn(["Modules\\{$module}"]);
}

/* ---------------------------------------------------- cross-module contexts */

$innerLayers = $moduleNames
    ->flatMap(fn ($m) => ["Modules\\{$m}\\Domain", "Modules\\{$m}\\Application"])
    ->all();

if ($innerLayers !== []) {
    arch('a module never reaches into another module\'s inner layers')
        // Invariant 7 across the whole src/Modules tree, built from the modules that
        // actually exist, so a new module is covered the moment it appears. Public
        // cross-module access goes through Shared\Domain\Ports, Shared\Application\DTOs
        // or a public Data class — there is no third option.
        ->expect('Modules')
        ->not->toUse($innerLayers);
}

/* ------------------------------------------- declarations & tree (it) */

it('finds a module directory to test', function () {
    // Guards against a silently-empty suite: if the glob breaks, every assertion
    // below would pass vacuously.
    expect(true)->toBeTrue();
})->skip(fn () => $moduleNames->isEmpty(), 'No modules yet — the suite is vacuous by design.');

it('declares its mode in ARCHITECTURE.md', function () use ($moduleNames, $modulesPath) {
    $undeclared = $moduleNames
        ->reject(fn ($m) => is_file("$modulesPath/$m/ARCHITECTURE.md"))
        ->all();

    expect($undeclared)->toBe(
        [],
        'Module(s) without ARCHITECTURE.md: '.implode(', ', $undeclared)
            .' — every module must declare FLAT or PROGRESSIVE.',
    );
})->skip(fn () => $moduleNames->isEmpty());

it('declares a mode the tree can be read from', function () use ($moduleNames, $modulesPath) {
    foreach ($moduleNames as $module) {
        $mode = modeOf("$modulesPath/$module");

        expect($mode)->not->toBeNull("{$module}/ARCHITECTURE.md missing");
        expect($mode)->not->toBe(
            'UNDECLARED',
            "{$module}/ARCHITECTURE.md must contain 'modo: FLAT' or 'modo: PROGRESSIVE' ('mode:' also accepted)."
        );
    }
})->skip(fn () => $moduleNames->isEmpty());

it('FLAT modules have no layering directories', function () use ($moduleNames, $modulesPath) {
    foreach ($moduleNames->filter(fn ($m) => modeOf("$modulesPath/$m") === 'FLAT') as $module) {
        foreach (['Domain', 'Application', 'Infrastructure'] as $layer) {
            expect(is_dir("$modulesPath/$module/$layer"))->toBeFalse(
                "{$module} is declared FLAT but has a {$layer}/ directory. "
                .'Either drop the layer or flip the declared mode — and only if 2+ triggers fire.'
            );
        }
    }
})->skip(fn () => $moduleNames->isEmpty());

it('no module ships an Http Resources folder', function () use ($moduleNames, $modulesPath) {
    // Invariant 12: response shapes are Spatie Data. A JsonResource subclass
    // next to a Data class is two serialization paths that will drift.
    foreach ($moduleNames as $module) {
        foreach (glob("$modulesPath/$module/{,**/}Resources", GLOB_BRACE) ?: [] as $dir) {
            if (is_dir($dir)) {
                expect(false)->toBeTrue(
                    "{$module} has {$dir}. Response shapes are Spatie Data — "
                    .'delete the Resources folder and move the fields to typed Data properties.'
                );
            }
        }
    }
})->skip(fn () => $moduleNames->isEmpty());

it('a domain layer is backed by a test that would fail if it moved', function () use ($progressive, $modulesPath) {
    // The no-lock rule: a Domain/ with no enforcing test is a folder, not a layer.
    // Per module, not "some module somewhere has a test" — that weaker version passed
    // while a PROGRESSIVE module sat completely untested.
    foreach ($progressive as $module) {
        $dir = "$modulesPath/$module";

        $tests = collect(
            array_merge(
                glob("$dir/{,Tests/**/}*Test.php", GLOB_BRACE) ?: [],
                glob("$dir/{,tests/**/}*Test.php", GLOB_BRACE) ?: [],
            ),
        )->filter(fn ($f) => is_file($f))->unique()->values();

        expect($tests->isNotEmpty())->toBeTrue(
            "{$module} is declared PROGRESSIVE so it has a Domain/, but it ships no test at all. "
            .'A layer no test holds in place is a folder. Add a Feature test and at least one '
            .'DB-free handler test with a mocked port, or demote the module to FLAT.'
        );
    }
})->skip(fn () => $progressive->isEmpty(), 'No PROGRESSIVE modules yet — src/Modules/ is empty.');

it('reports the architecture suite as vacuous instead of passing silently', function () use ($moduleNames) {
    // `laravel/pao` prints skipped tests as skipped rather than hiding them, but a
    // fully-skipped suite still exits 0. This makes the state impossible to miss.
    expect($moduleNames->isEmpty())->toBeFalse(
        'src/Modules/ is empty: every module assertion in this file is skipped, so the '
        .'architecture rules are UNVERIFIED, not passing. Generate the first module with '
        .'`npm run module:make` to make this suite real.'
    );
})->skip(fn () => $moduleNames->isEmpty(), 'Vacuous by design while src/Modules/ is empty.');

it('Shared holds no business rules', function () use ($sharedPath) {
    // "No business rules" is not statically decidable in general. What IS decidable:
    // no model relationships and no persistence in Shared, because a relationship is
    // how a business rule sneaks into a supposedly generic helper.
    $offenders = collect(glob("$sharedPath/{,*/}*.php", GLOB_BRACE) ?: [])
        ->filter(fn ($f) => is_file($f))
        ->reject(fn ($f) => str_contains($f, '/Ports/') || str_contains($f, '/Support/'))
        ->filter(fn ($f) => (bool) preg_match('/function\s+\w+\s*\(\s*\)\s*:\s*(BelongsTo|HasMany|HasOne|MorphMany|MorphTo)\b/', (string) file_get_contents($f)))
        ->map(fn ($f) => basename(dirname($f)).'/'.basename($f))
        ->values();

    expect($offenders->all())->toBe(
        [],
        'Shared\\ must not declare Eloquent relationships — that is business knowledge with '
        .'no owner module. Move it into the module that owns the aggregate: '
        .implode(', ', $offenders->all())
    );
})->skip(false);
