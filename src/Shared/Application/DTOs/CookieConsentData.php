<?php

declare(strict_types=1);

namespace Shared\Application\DTOs;

use Spatie\LaravelData\Data;

/**
 * The cookie-consent banner contract, shared with EVERY page through
 * `HandleInertiaRequests::share()`.
 *
 * `null` is the normal case, not the exception: it means "this visitor already
 * consented" (or the banner is switched off), and the Vue banner renders
 * nothing. That keeps the decision server-owned — the client never re-derives
 * consent from `document.cookie`, which is what the package's own Blade+JS
 * banner does and why it cannot be made HttpOnly.
 *
 * Property names are camelCase on the wire, deliberately: unlike
 * {@see SoftDeleteFilterData} this DTO is never a request payload, it is an
 * Inertia prop read by TypeScript, and the generated `generated.d.ts` names the
 * fields exactly as written here. No `MapName` mapper, no name drift.
 */
final class CookieConsentData extends Data
{
    public function __construct(
        /** Banner body copy, resolved from the package's published `en` texts. */
        public readonly string $message,
        /** Label of the single "allow" button — the only choice this banner offers. */
        public readonly string $acceptLabel,
        /**
         * Where to POST the decision, resolved through `route()` so the URL is
         * declared once in `routes/web.php` and never duplicated as a string
         * literal in a `.vue` file.
         */
        public readonly string $endpoint,
    ) {}
}
