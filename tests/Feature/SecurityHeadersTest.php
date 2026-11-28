<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

/*
 * Railway terminates TLS at its edge proxy. These tests pin the two halves of
 * that contract: Laravel must trust X-Forwarded-Proto so @vite/asset() emit
 * HTTPS URLs, and the CSP must stay strict while adding the mixed-content
 * safety net only when the request itself is secure.
 */

/*
 * `npm run dev` writes public/hot. Asset assertions must read the build
 * manifest, not whatever dev server happens to be running — otherwise this
 * suite is green only when the developer stops Vite first.
 */
beforeEach(function () {
    Vite::useHotFile(storage_path('framework/testing/vite.hot'));

    // Deterministic external origins: the suite must not depend on the
    // developer's .env R2 bucket or on Cloudflare's r2.dev host.
    config([
        'filesystems.disks.r2.url' => 'https://cdn.aquashield.test',
        'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        'filesystems.disks.r2.bucket' => 'aquashield-bucket',
        'filesystems.disks.r2.use_path_style_endpoint' => true,
    ]);
});

it('emits https asset urls when the proxy terminates tls', function () {
    $response = $this->get('/', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'aquashield-production.up.railway.app',
    ]);

    $response->assertOk();

    $html = (string) $response->getContent();

    /*
     * Read the concrete asset names from the manifest instead of naming one.
     * The old assertion pinned a font file, so removing the unused Inter
     * pipeline turned an HTTPS-proxy check into a font-existence check. The
     * contract under test is "every build asset is absolute and HTTPS", and
     * the two entry files are always present.
     */
    $manifest = json_decode(
        (string) file_get_contents(public_path('build/manifest.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $entries = is_array($manifest) ? $manifest : [];

    $jsEntry = $entries['resources/js/app.tsx'] ?? null;
    $cssEntry = $entries['resources/css/app.css'] ?? null;

    expect($jsEntry)->toBeArray();
    expect($cssEntry)->toBeArray();

    $jsFile = is_array($jsEntry) && is_string($jsEntry['file'] ?? null) ? $jsEntry['file'] : '';
    $cssFile = is_array($cssEntry) && is_string($cssEntry['file'] ?? null) ? $cssEntry['file'] : '';

    expect($jsFile)->not->toBe('');
    expect($cssFile)->not->toBe('');

    expect($html)
        ->toContain('https://aquashield-production.up.railway.app/build/assets/')
        ->toContain('https://aquashield-production.up.railway.app/build/'.$jsFile)
        ->toContain('https://aquashield-production.up.railway.app/build/'.$cssFile)
        ->not->toContain('http://aquashield-production.up.railway.app/build/assets/');
});

it('keeps the nonce strict csp and upgrades insecure subresources over https', function () {
    $response = $this->get('/', [
        'X-Forwarded-Proto' => 'https',
    ]);

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("font-src 'self'")
        ->toContain("style-src-elem 'self' 'nonce-")
        ->not->toContain("style-src-elem 'self' 'unsafe-inline'")
        ->toContain("style-src-attr 'unsafe-inline'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("'strict-dynamic'")
        ->toContain('upgrade-insecure-requests');

    // Vite's dev client and Inertia's runtime <style> tags read this nonce;
    // it must be the same value the header allows.
    preg_match("/style-src-elem 'self' 'nonce-([^']+)'/", $csp, $matches);

    expect((string) $response->getContent())
        ->toContain('property="csp-nonce"')
        ->toContain('nonce="'.$matches[1].'"');
});

it('does not add the upgrade directive to plain http requests', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('upgrade-insecure-requests');
    expect($csp)->not->toContain('http://localhost:5173');
});

it('allows the vite dev server origin in local', function () {
    $this->app->detectEnvironment(fn (): string => 'local');

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('http://localhost:5173')
        ->toContain("'unsafe-eval'")
        ->not->toContain('upgrade-insecure-requests');
});

it('allows the google maps and r2 origins without wildcards', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('https://maps.googleapis.com')
        ->toContain('https://places.googleapis.com')
        ->toContain('https://cdn.aquashield.test')
        ->toContain('https://account.r2.cloudflarestorage.com')
        ->not->toContain('*.googleapis.com')
        ->not->toContain('*.r2.cloudflarestorage.com')
        ->not->toContain('*.r2.dev');
});

it('omits r2 origins when the r2 disk has no public url or endpoint', function () {
    config([
        'filesystems.disks.r2.url' => null,
        'filesystems.disks.r2.endpoint' => null,
    ]);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->not->toContain('r2.cloudflarestorage.com')
        ->not->toContain('cdn.aquashield.test');
});

it('derives the signed-url origin from the bucket when path style is disabled', function () {
    config([
        'filesystems.disks.r2.use_path_style_endpoint' => false,
        'filesystems.disks.r2.bucket' => 'aquashield-bucket',
    ]);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('https://aquashield-bucket.account.r2.cloudflarestorage.com')
        ->not->toContain('https://account.r2.cloudflarestorage.com');
});

it('strips the path from the configured r2 public url', function () {
    config([
        'filesystems.disks.r2.url' => 'https://cdn.aquashield.test:8443/assets/',
    ]);

    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('https://cdn.aquashield.test:8443')
        ->not->toContain('https://cdn.aquashield.test:8443/assets');
});

it('emits transport-safe headers on API responses without an Inertia CSP', function (): void {
    $response = $this->getJson('/api/v1/availability-rules');

    $response->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
});
