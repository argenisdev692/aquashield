import { describe, expect, it } from 'vitest';
import { sparklineGeometry } from '@/lib/sparkline';

describe('sparklineGeometry', () => {
    it('returns null when there is no trend to draw', () => {
        expect(sparklineGeometry([])).toBeNull();
        expect(sparklineGeometry([4])).toBeNull();
    });

    it('maps every point inside the viewBox', () => {
        const geometry = sparklineGeometry([0, 4, 1, 8, 2]);
        const points = geometry?.line
            .replace('M', '')
            .split(' L')
            .map((point) => point.split(',').map(Number));

        expect(points).toHaveLength(5);

        for (const [x, y] of points ?? []) {
            expect(x).toBeGreaterThanOrEqual(2);
            expect(x).toBeLessThanOrEqual(118);
            expect(y).toBeGreaterThanOrEqual(2);
            expect(y).toBeLessThanOrEqual(30);
        }
    });

    it('draws a flat series as a flat line and closes the area', () => {
        const geometry = sparklineGeometry([0, 0, 0], 120, 32, 2);

        expect(geometry?.line).toBe('M2,30 L60,30 L118,30');
        expect(geometry?.area).toBe('M2,30 L60,30 L118,30 L118,32 L2,32 Z');
    });

    it('puts the peak on the top padding and the trough on the bottom', () => {
        const geometry = sparklineGeometry([1, 9], 120, 32, 2);

        expect(geometry?.line).toBe('M2,30 L118,2');
    });

    it('keeps fractional coordinates compact', () => {
        const geometry = sparklineGeometry([0, 1, 2], 100, 30, 0);

        expect(geometry?.line).toBe('M0,30 L50,15 L100,0');
    });
});
