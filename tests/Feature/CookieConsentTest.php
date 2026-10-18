<?php

declare(strict_types=1);

/*
 * Cookie consent, end to end through the real HTTP stack.
 *
 * The assertions that matter are the ones the package's own Blade+JS banner
 * cannot make: the cookie is written by the SERVER, so it is HttpOnly, and it
 * is readable on the next request without any JavaScript having run.
 */

use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Cookie;

/** The one cookie this feature is allowed to write. */
const CONSENT_COOKIE = 'laravel_cookie_consent';

it('offers the banner to a visitor who has not consented', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('cookieConsent.message', 'Your experience on this site will be improved by allowing cookies.')
            ->where('cookieConsent.acceptLabel', 'Allow cookies')
            ->where('cookieConsent.endpoint', '/cookie-consent')
        );
});

it('withholds the banner once consent is on the request', function () {
    $this->withUnencryptedCookie(CONSENT_COOKIE, '1')
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('cookieConsent', null));
});

it('records consent in an unencrypted, HttpOnly cookie', function () {
    $response = $this->post('/cookie-consent');

    $response->assertOk()->assertJson(['consented' => true]);

    // `false` = the cookie must NOT be encrypted. The package's service provider
    // calls `disableFor()` on this name precisely so it stays readable; without
    // it the visitor would be asked again on every single page.
    $response->assertCookie(CONSENT_COOKIE, '1', encrypted: false);

    $cookie = collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === CONSENT_COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getSameSite())->toBe('lax')
        // null domain = host-only. The package's JS wrote `domain=<host>` and a
        // host with a port silently dropped the cookie.
        ->and($cookie->getDomain())->toBeNull();
});

it('honours the configured lifetime', function () {
    config(['cookie-consent.cookie_lifetime' => 7]);

    $cookie = collect($this->post('/cookie-consent')->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === CONSENT_COOKIE);

    expect($cookie)->not->toBeNull()
        // `expires` is ~7 days out; allow a minute of slack for the request itself.
        ->and($cookie->getExpiresTime())->toBeGreaterThan(time() + (7 * 24 * 60 * 60) - 60)
        ->and($cookie->getExpiresTime())->toBeLessThan(time() + (7 * 24 * 60 * 60) + 60);
});

it('answers 404 and shows nothing when the banner is switched off', function () {
    config(['cookie-consent.enabled' => false]);

    // A tab loaded while consent was on can still be holding the endpoint. The
    // capability does not exist, so it must not answer 200 and write a record
    // nothing will ever honour.
    $this->post('/cookie-consent')->assertNotFound();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('cookieConsent', null));
});

it('requires a cross-site-safe method and a CSRF token', function () {
    // A GET must not fall through to the controller: `post()` routes 405 on a
    // wrong verb, and this is where that is proven rather than assumed.
    $this->get('/cookie-consent')->assertMethodNotAllowed();

    $this->post('/cookie-consent')
        ->assertOk(); // same-origin Inertia XHR sends the XSRF header
});
