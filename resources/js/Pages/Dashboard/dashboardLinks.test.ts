import { describe, expect, it } from 'vitest';
import { dashboardMetricLinks } from './dashboardLinks';

const params = (url: string): URLSearchParams => new URL(url, 'https://example.test').searchParams;

describe('dashboardMetricLinks', () => {
    it('carries the selected window into every KPI destination', () => {
        const links = dashboardMetricLinks('2026-11-01', '2026-11-30');

        for (const href of Object.values(links)) {
            const query = params(href);

            expect(query.get('filter[date_from]')).toBe('2026-11-01');
            expect(query.get('filter[date_to]')).toBe('2026-11-30');
        }
    });

    it('keeps the metric-specific filters next to the window', () => {
        const links = dashboardMetricLinks('2026-11-01', '2026-11-30');

        expect(params(links.inspections).get('filter[kind]')).toBe('inspection');
        expect(params(links.won).get('filter[status]')).toBe('won');
        expect(params(links.newLeads).get('filter[status]')).toBeNull();
        expect(params(links.appointments).get('filter[kind]')).toBeNull();
    });
});
