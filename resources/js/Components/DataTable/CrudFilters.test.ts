// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { presetRange } from './CrudFilters';

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-27T02:00:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('date presets', () => {
    it('builds UTC calendar ranges independently of the browser timezone', () => {
        expect(presetRange('last7', 'UTC')).toEqual({ from: '2026-11-21', to: '2026-11-27' });
        expect(presetRange('last30', 'UTC')).toEqual({ from: '2026-10-29', to: '2026-11-27' });
        expect(presetRange('month', 'UTC')).toEqual({ from: '2026-11-01', to: '2026-11-27' });
        expect(presetRange('year', 'UTC')).toEqual({ from: '2026-01-01', to: '2026-11-27' });
        expect(presetRange('all', 'UTC')).toEqual({ from: null, to: null });
    });
});
