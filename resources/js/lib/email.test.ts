import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { ALLOWED_DOMAINS, BLOCKED_DOMAINS } from './email';

/**
 * The TS allow/block lists are a mirror of `App\Rules\AllowedEmailDomain`. On a
 * staff directory, one drifted list means the form accepts an address the
 * server rejects (or the reverse). Reading the PHP constant in a test is the
 * cheapest lockstep there is.
 */
const php = readFileSync(
    new URL('../../../app/Rules/AllowedEmailDomain.php', import.meta.url),
    'utf8',
);

const phpList = (name: 'ALLOWED' | 'BLOCKED'): string[] => {
    const match = new RegExp(`const array ${name} = \\[([\\s\\S]*?)\\];`).exec(php);

    if (match === null) {
        throw new Error(`Could not read ${name} from AllowedEmailDomain.php`);
    }

    return [...match[1].matchAll(/'([^']+)'/g)].map((entry) => entry[1]);
};

const sorted = (values: readonly string[]): string[] => [...new Set(values)].sort();

describe('email domain allowlist parity', () => {
    it('matches the PHP allowed list', () => {
        expect(sorted(ALLOWED_DOMAINS)).toEqual(sorted(phpList('ALLOWED')));
    });

    it('matches the PHP blocked list', () => {
        expect(sorted(BLOCKED_DOMAINS)).toEqual(sorted(phpList('BLOCKED')));
    });
});
