/**
 * Ambient type declarations for the Inertia app.
 *
 * The `import` is load-bearing: without it this file is a global script and
 * `declare module` REPLACES `@inertiajs/core`'s types instead of augmenting
 * them.
 *
 * `sharedPageProps` is everything `HandleInertiaRequests::share()` puts on every
 * page. The shape comes from `resources/js/generated/generated.d.ts`, written by
 * `php artisan typescript:transform` from the Spatie Data class on the server —
 * never hand-written, because a hand-written mirror is exactly how `any` and
 * silent drift get in.
 */
import '@inertiajs/core';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            /**
             * Non-null only while this visitor still owes a cookie-consent
             * decision. Null once the consent cookie is on the request, or when
             * `COOKIE_CONSENT_ENABLED` is off — so `null` is the common case and
             * the banner renders nothing for it.
             */
            cookieConsent: Shared.Application.DTOs.CookieConsentData | null;
        };
    }
}
