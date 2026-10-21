<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Branding;

/**
 * The ONE brand palette every server-rendered asset draws from: AI image
 * prompts, deterministic SVG diagrams, transactional email layouts, and PDF
 * exports. Values are static design tokens, not per-tenant data.
 *
 * Single source of truth: `resources/css/globals.css`. Each constant below
 * names the exact custom property it mirrors — there is no second palette and
 * no per-module override. `resources/css/app.css` only re-exports these tokens
 * to Tailwind and defines no colour of its own, so keeping this class in step
 * with globals.css keeps the whole application on one palette.
 *
 * Why a PHP mirror exists at all: none of the consumers above can read a CSS
 * custom property. Email clients do not support `var()`, DomPDF resolves no
 * cascade, and an image prompt is a string sent to a provider. The mirror is
 * the price of that; a SECOND mirror is not, which is why
 * `resources/views/emails/layout.blade.php` reads these constants rather than
 * keeping its own copy of the hexes.
 *
 * Verified against globals.css on 2026-10-21. Before this, the class still held
 * the palette of a different product — a violet `#7c3aed` primary over a
 * `#050714` ground — while the docblock pointed at `--hub-bg-dark`,
 * `--background` and `--card`, three properties that exist in no stylesheet
 * here. Nothing consumed the class yet, so no violet shipped, but any module
 * that trusted it would have rendered a brand that appears nowhere in the UI.
 * Assert through these constants, never against a hex literal, or the same
 * drift passes its tests again.
 */
final class BrandPalette
{
    /** `--color-aq-void` — the page ground, and the theme's darkest surface. */
    public const string BACKGROUND = '#081829';

    /** `--color-aq-base` — the default card/plane the UI sits on. */
    public const string SURFACE = '#0c2340';

    /** `--color-aq-elevated` — the raised rung: popovers, secondary cards. */
    public const string ELEVATED_SURFACE = '#1a3a5c';

    /**
     * `--color-aq-overlay` — the solid hairline, used where a consumer cannot
     * composite the translucent `--aq-line` (aqua at 22%) the UI paints with.
     */
    public const string BORDER = '#244166';

    /** `--color-aq-accent` / `--p-primary-500` — the brand signal, one hue. */
    public const string PRIMARY_ACCENT = '#00b5e2';

    /** `--color-aq-accent-soft` — the lightened accent that stays legible on the night navy. */
    public const string PRIMARY_ACCENT_SOFT = '#33c4e8';

    /** `--color-aq-ink` — headings and primary copy. */
    public const string TEXT_PRIMARY = '#f0eeff';

    /** `--color-aq-ink-soft` — body copy that recedes from a heading without reading as disabled. */
    public const string TEXT_SECONDARY = '#b8c5d6';

    /** `--color-aq-ink-muted` — labels, metadata, the quietest legible rung. */
    public const string TEXT_MUTED = '#7a8fa8';

    /**
     * `--aq-grad-primary`, composed from the two constants it is made of rather
     * than restated as a literal — the CSS token and this string cannot drift
     * apart in the endpoints while they share the same source colours.
     */
    #[\NoDiscard]
    public static function gradientTech(): string
    {
        return 'linear-gradient(135deg, '.self::SURFACE.' 0%, '.self::PRIMARY_ACCENT.' 100%)';
    }
}
