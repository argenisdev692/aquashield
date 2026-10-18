<?php

declare(strict_types=1);

namespace Shared\Application\Queries;

use Illuminate\Http\Request;
use Shared\Application\DTOs\CookieConsentData;
use Shared\Infrastructure\Http\Controllers\AcceptCookieConsentController;

/**
 * Decides whether the current visitor still owes a cookie-consent decision, and
 * hands the banner everything it needs when they do.
 *
 * The cookie name comes from `config('cookie-consent.cookie_name')` — the one
 * place it exists, written by {@see AcceptCookieConsentController} and read
 * back here. Nothing in this class hardcodes it.
 *
 * `$request->hasCookie()` reads the INBOUND jar. The package's own view
 * composer calls `Cookie::has()`, which reads the RESPONSE jar and is therefore
 * a weaker statement about the same fact.
 */
final class PendingCookieConsent
{
    /**
     * @return CookieConsentData|null null when nothing is owed: consent already
     *                                recorded, or the banner is switched off via
     *                                `COOKIE_CONSENT_ENABLED`.
     */
    public function __invoke(Request $request): ?CookieConsentData
    {
        if (! (bool) config('cookie-consent.enabled')) {
            return null;
        }

        if ($request->hasCookie((string) config('cookie-consent.cookie_name'))) {
            return null;
        }

        return new CookieConsentData(
            message: (string) trans('cookie-consent::texts.message'),
            acceptLabel: (string) trans('cookie-consent::texts.agree'),
            // `absolute: false` so the prop is a path, not a scheme+host. The
            // banner posts it back to the origin it was served from, and baking
            // `http://localhost:8000` into a shared prop would leak the local
            // environment into every rendered page.
            endpoint: route('cookie-consent.accept', absolute: false),
        );
    }
}
