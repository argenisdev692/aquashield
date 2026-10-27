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
});

it('emits https asset urls when the proxy terminates tls', function () {
    $response = $this->get('/', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'aquashield-production.up.railway.app',
    ]);

    $response->assertOk();

    $html = (string) $response->getContent();

    expect($html)
        ->toContain('https://aquashield-production.up.railway.app/build/assets/')
        ->toContain('https://aquashield-production.up.railway.app/build/assets/inter-500-normal-')
        ->not->toContain('http://aquashield-production.up.railway.app/build/assets/');
});

it('keeps the nonce strict csp and upgrades insecure subresources over https', function () {
    $csp = (string) $this->get('/', [
        'X-Forwarded-Proto' => 'https',
    ])->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("font-src 'self'")
        ->toContain("style-src-elem 'self' 'unsafe-inline'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain('upgrade-insecure-requests');
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
