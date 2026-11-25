import { describe, expect, it } from 'vitest';
import { regionForAddress, regionTimezone } from './regions';

describe('regionTimezone', () => {
    it('maps the Texas metros to Central and Florida to Eastern', () => {
        expect(regionTimezone('houston')).toBe('America/Chicago');
        expect(regionTimezone('dallas')).toBe('America/Chicago');
        expect(regionTimezone('florida')).toBe('America/New_York');
    });

    it('returns null when the address did not resolve to a service region', () => {
        expect(regionTimezone(null)).toBeNull();
        expect(regionTimezone('')).toBeNull();
        expect(regionTimezone('austin')).toBeNull();
    });
});

describe('regionForAddress', () => {
    it('prefers Florida by state and Central Texas by ZIP', () => {
        expect(regionForAddress('FL', 'Miami', '33101')).toBe('florida');
        expect(regionForAddress('TX', 'Katy', '77494')).toBe('houston');
        expect(regionForAddress('TX', 'Plano', '75024')).toBe('dallas');
    });
});
