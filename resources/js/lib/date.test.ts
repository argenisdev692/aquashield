import { describe, expect, it } from 'vitest';
import { formatUtcDateTime, formatWallClockDateTime } from './date';

describe('formatUtcDateTime', () => {
    it('renders the UTC calendar day even when the instant is tomorrow locally', () => {
        const formatted = formatUtcDateTime('2026-11-27T02:00:00+00:00');

        expect(formatted).toContain('Nov 27, 2026');
        expect(formatted).toContain('UTC');
    });
});

describe('formatWallClockDateTime', () => {
    it('keeps the wall clock encoded by the server offset', () => {
        const formatted = formatWallClockDateTime('2026-11-28T10:00:00-05:00', 'en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: false,
        });

        expect(formatted).toBe('10:00');
    });

    it('returns an empty string for a missing value', () => {
        expect(formatWallClockDateTime(null)).toBe('');
    });
});
