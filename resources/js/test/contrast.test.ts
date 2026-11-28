/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

/**
 * WCAG 2.2 AA contrast, computed from the token layer instead of measured in a
 * browser. jsdom cannot paint, so `axe` disables `color-contrast`; this suite is
 * the other half: it reads `globals.css`, resolves the raw hex tokens and
 * asserts the pairs that actually carry text.
 *
 * It exists because a measured review found `dark:text-surface-400` on a dark
 * card at **4.25:1** — below the 4.5 floor — across 100+ call sites. The fix was
 * to use the token the file already documents as the muted text colour
 * (`--p-text-muted-color: var(--p-surface-300)`), so these assertions keep the
 * two in lockstep.
 */
const CSS = readFileSync(fileURLToPath(new URL('../../css/globals.css', import.meta.url)), 'utf8');

type Tokens = Record<string, string>;

/** Collect `--token: #hex` per top-level block (`:root`, `.dark`, `@theme`). */
const parseTokens = (css: string): Record<string, Tokens> => {
    const blocks: Record<string, Tokens> = {};
    let selector: string | null = null;

    for (const rawLine of css.split('\n')) {
        const line = rawLine.trim();

        const open = /^(:root|\.dark|@theme)\s*\{$/.exec(line);

        if (open !== null) {
            selector = open[1];
            blocks[selector] ??= {};

            continue;
        }

        if (line === '}') {
            selector = null;

            continue;
        }

        if (selector === null) {
            continue;
        }

        const token = /^(--[\w-]+):\s*(#[0-9a-fA-F]{3,8})\s*;/.exec(line);

        if (token !== null) {
            blocks[selector][token[1]] = token[2];
        }
    }

    return blocks;
};

const blocks = parseTokens(CSS);

type Mode = 'light' | 'dark' | 'brand';

/**
 * Resolve inside the cascade for the mode the pair is rendered in. `.dark`
 * overrides only exist for a handful of tokens, so the order matters: reading
 * `.dark` first for a light pair would return the dark brand colour.
 */
const token = (name: string, mode: Mode): string => {
    const order: Record<Mode, Array<Tokens | undefined>> = {
        light: [blocks[':root'], blocks['@theme']],
        dark: [blocks['.dark'], blocks[':root'], blocks['@theme']],
        brand: [blocks['@theme'], blocks[':root']],
    };

    for (const block of order[mode]) {
        const value = block?.[name];

        if (value !== undefined) {
            return value;
        }
    }

    throw new Error(`Token ${name} not found in globals.css (${mode})`);
};

const channel = (value: number): number => {
    const srgb = value / 255;

    return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
};

const luminance = (hex: string): number => {
    const normalized = hex.replace('#', '');
    const full =
        normalized.length === 3
            ? normalized
                  .split('')
                  .map((part) => part + part)
                  .join('')
            : normalized;

    const [r, g, b] = [0, 2, 4].map((offset) =>
        channel(Number.parseInt(full.slice(offset, offset + 2), 16)),
    );

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const contrast = (foreground: string, background: string): number => {
    const a = luminance(foreground);
    const b = luminance(background);

    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
};

interface Pair {
    label: string;
    foreground: string;
    background: string;
    mode: Mode;
}

const AA = 4.5;
const NON_TEXT = 3;

const mix = (foreground: string, background: string, alpha: number): string => {
    const parse = (hex: string): number[] => {
        const normalized = hex.replace('#', '');
        const full =
            normalized.length === 3
                ? normalized
                      .split('')
                      .map((part) => part + part)
                      .join('')
                : normalized;

        return [0, 2, 4].map((offset) => Number.parseInt(full.slice(offset, offset + 2), 16));
    };

    const fg = parse(foreground);
    const bg = parse(background);
    const blended = fg.map((component, index) =>
        Math.round(component * alpha + (bg[index] ?? 0) * (1 - alpha)),
    );

    return `#${blended.map((component) => component.toString(16).padStart(2, '0')).join('')}`;
};

interface TintPair {
    label: string;
    foreground: string;
    tint: string;
    base: string;
    mode: Mode;
}

const tintPairs: TintPair[] = [
    {
        label: 'success tag text on its 10% tint',
        foreground: '--color-aq-success-strong',
        tint: '--color-aq-success',
        base: '--p-surface-0',
        mode: 'light',
    },
    {
        label: 'warning tag text on its 10% tint',
        foreground: '--color-aq-warning-strong',
        tint: '--color-aq-warning',
        base: '--p-surface-0',
        mode: 'light',
    },
    {
        label: 'danger tag text on its 10% tint',
        foreground: '--color-aq-danger-strong',
        tint: '--color-aq-danger',
        base: '--p-surface-0',
        mode: 'light',
    },
    {
        label: 'success tag text on its dark 10% tint',
        foreground: '--color-aq-success-strong',
        tint: '--color-aq-success',
        base: '--p-surface-900',
        mode: 'dark',
    },
    {
        label: 'warning tag text on its dark 10% tint',
        foreground: '--color-aq-warning-strong',
        tint: '--color-aq-warning',
        base: '--p-surface-900',
        mode: 'dark',
    },
    {
        label: 'danger tag text on its dark 10% tint',
        foreground: '--color-aq-danger-strong',
        tint: '--color-aq-danger',
        base: '--p-surface-900',
        mode: 'dark',
    },
];

const pairs: Pair[] = [
    {
        label: 'body text on the light canvas',
        foreground: '--p-surface-700',
        background: '--p-surface-50',
        mode: 'light',
    },
    {
        label: 'secondary text on a light card',
        foreground: '--p-surface-600',
        background: '--p-surface-0',
        mode: 'light',
    },
    {
        label: 'muted text on the light canvas',
        foreground: '--p-surface-500',
        background: '--p-surface-50',
        mode: 'light',
    },
    {
        label: 'body text on a dark card',
        foreground: '--p-surface-300',
        background: '--p-surface-900',
        mode: 'dark',
    },
    {
        label: 'body text on the dark canvas',
        foreground: '--p-surface-300',
        background: '--p-surface-950',
        mode: 'dark',
    },
    {
        label: 'muted text on an elevated dark surface',
        foreground: '--p-surface-300',
        background: '--p-surface-800',
        mode: 'dark',
    },
    {
        label: 'brand ink on the auth shell',
        foreground: '--color-aq-ink',
        background: '--color-aq-void',
        mode: 'brand',
    },
    {
        label: 'soft ink on the auth shell',
        foreground: '--color-aq-ink-soft',
        background: '--color-aq-void',
        mode: 'brand',
    },
    {
        label: 'muted ink on the auth shell',
        foreground: '--color-aq-ink-muted',
        background: '--color-aq-void',
        mode: 'brand',
    },
    {
        label: 'accent link on the auth shell',
        foreground: '--color-aq-accent',
        background: '--color-aq-void',
        mode: 'brand',
    },
    {
        label: 'warning text on the auth shell',
        foreground: '--color-aq-warning',
        background: '--color-aq-void',
        mode: 'brand',
    },
    {
        label: 'error text on a light card',
        foreground: '--color-aq-danger-strong',
        background: '--p-surface-0',
        mode: 'light',
    },
    {
        label: 'error text on a dark card',
        foreground: '--color-aq-danger-strong',
        background: '--p-surface-900',
        mode: 'dark',
    },
    {
        label: 'accent text on a light card',
        foreground: '--color-aq-accent-text',
        background: '--p-surface-0',
        mode: 'light',
    },
    {
        label: 'accent text on a dark card',
        foreground: '--color-aq-accent-text',
        background: '--p-surface-900',
        mode: 'dark',
    },
];

describe('design token contrast (WCAG AA, normal text)', () => {
    it('parses the token blocks it depends on', () => {
        expect(Object.keys(blocks)).toEqual(expect.arrayContaining([':root', '.dark', '@theme']));
        expect(blocks[':root']['--p-surface-900']).toMatch(/^#[0-9a-fA-F]{6}$/);
    });

    it.each(pairs)('$label meets 4.5:1', ({ foreground, background, mode }) => {
        const fg = token(foreground, mode);
        const bg = token(background, mode);
        const ratio = contrast(fg, bg);

        expect(
            Number(ratio.toFixed(2)),
            `${foreground} (${fg}) on ${background} (${bg}) = ${ratio.toFixed(2)}:1`,
        ).toBeGreaterThanOrEqual(AA);
    });

    it.each(tintPairs)('$label meets 4.5:1', ({ foreground, tint, base, mode }) => {
        const fg = token(foreground, mode);
        const background = mix(token(tint, mode), token(base, mode), 0.1);
        const ratio = contrast(fg, background);

        expect(
            Number(ratio.toFixed(2)),
            `${foreground} (${fg}) on ${tint} @10% over ${base} (${background}) = ${ratio.toFixed(2)}:1`,
        ).toBeGreaterThanOrEqual(AA);
    });

    it('keeps the focus indicator above the non-text 3:1 floor', () => {
        const backgrounds = { light: '--p-surface-0', dark: '--p-surface-900' } as const;

        for (const mode of ['light', 'dark'] as const) {
            const ratio = contrast(token('--color-aq-focus', mode), token(backgrounds[mode], mode));

            expect(Number(ratio.toFixed(2))).toBeGreaterThanOrEqual(NON_TEXT);
        }
    });

    it('keeps control borders above the non-text 3:1 floor', () => {
        const pairs = {
            light: { border: '--p-surface-500', backgrounds: ['--p-surface-0', '--p-surface-50'] },
            dark: {
                border: '--p-surface-400',
                backgrounds: ['--p-surface-950', '--p-surface-900'],
            },
        } as const;

        for (const mode of ['light', 'dark'] as const) {
            for (const background of pairs[mode].backgrounds) {
                const ratio = contrast(token(pairs[mode].border, mode), token(background, mode));

                expect(Number(ratio.toFixed(2))).toBeGreaterThanOrEqual(NON_TEXT);
            }
        }
    });

    it('documents why surface-400 is not a text token on a card', () => {
        // The file says so next to `--p-text-muted-color`: -400 reads at 4.25:1
        // on surface-900. Pinning it keeps a future palette edit from promoting
        // it back to text (the 100+ `dark:text-surface-400` call sites).
        const ratio = contrast(
            token('--p-surface-400', 'light'),
            token('--p-surface-900', 'light'),
        );

        expect(Number(ratio.toFixed(2))).toBeLessThan(AA);
    });
});
