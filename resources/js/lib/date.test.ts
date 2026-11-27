import { describe, expect, it } from 'vitest';
import { formatUtcDateTime } from './date';

describe('formatUtcDateTime', () => {
    it('renders the UTC calendar day even when the instant is tomorrow locally', () => {
        const formatted = formatUtcDateTime('2026-11-27T02:00:00+00:00');

        expect(formatted).toContain('Nov 27, 2026');
        expect(formatted).toContain('UTC');
    });
});
