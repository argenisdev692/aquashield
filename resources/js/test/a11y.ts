/**
 * Structural accessibility checks for the jsdom suites.
 *
 * `axe-core` in jsdom is a **structural** check: it validates roles, names,
 * relationships (`aria-labelledby`/`aria-describedby`/`aria-controls`), state
 * invariants and heading order. It cannot validate what needs layout or paint,
 * so two rules are disabled here — and only these two:
 *
 * - `color-contrast`: every element reports no rendered size in jsdom, so the
 *   rule cannot resolve background colours. Contrast is verified against the
 *   token pairs in `test/contrast.test.ts`.
 * - `region`: these suites mount a component fragment, not a page. "All content
 *   must be in a landmark" is a page-level rule and the page shell
 *   (`AppLayout`/`AuthLayout`) owns it.
 *
 * It returns the violations instead of throwing so the call site keeps a
 * visible `expect(...).toEqual([])` — a helper that asserts for you is
 * invisible to the test reader and to `vitest/expect-expect`.
 */
import axe from 'axe-core';

const DISABLED = ['color-contrast', 'region'] as const;

export interface A11yViolation {
    id: string;
    impact: string | null | undefined;
    help: string;
    nodes: string[];
}

export async function a11yViolations(container: HTMLElement): Promise<A11yViolation[]> {
    const results = await axe.run(container, {
        rules: Object.fromEntries(DISABLED.map((rule) => [rule, { enabled: false }])),
    });

    return results.violations.map((violation) => ({
        id: violation.id,
        impact: violation.impact,
        help: violation.help,
        nodes: violation.nodes.map((node) => node.target.join(' ')),
    }));
}
