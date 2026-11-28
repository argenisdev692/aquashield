import { describe, expect, it } from 'vitest';
import { dashboardMetricLinks } from './dashboardLinks';

const params = (url: string): URLSearchParams => new URL(url, 'https://example.test').searchParams;

describe('dashboardMetricLinks', () => {
    it('carries the selected window and board scope into every KPI destination', () => {
        const links = dashboardMetricLinks('2026-11-01', '2026-11-30', {
            region: 'dallas',
            source: 'meta',
        });

        for (const href of Object.values(links)) {
            const query = params(href);

            expect(query.get('filter[region]')).toBe('dallas');
            expect(query.get('filter[source]')).toBe('meta');
        }
    });

    it('uses the metric-specific population instead of a shared created window', () => {
        const links = dashboardMetricLinks('2026-11-01', '2026-11-30');

        expect(params(links.newLeads).get('filter[date_from]')).toBe('2026-11-01');
        expect(params(links.newLeads).get('filter[date_to]')).toBe('2026-11-30');

        expect(params(links.won).get('filter[status]')).toBe('won');
        expect(params(links.won).get('filter[converted_from]')).toBe('2026-11-01');
        expect(params(links.won).get('filter[converted_to]')).toBe('2026-11-30');
        expect(params(links.won).get('filter[date_from]')).toBeNull();

        expect(params(links.inspections).get('filter[kind]')).toBe('inspection');
        expect(params(links.inspections).get('filter[scheduled_only]')).toBe('1');
        expect(params(links.appointments).get('filter[kind]')).toBeNull();
        expect(params(links.appointments).get('filter[scheduled_only]')).toBe('1');
    });

    it('does not leak a table-only status filter into the KPI destinations', () => {
        const links = dashboardMetricLinks('2026-11-01', '2026-11-30', {
            status: 'lost',
            region: 'dallas',
        });

        expect(params(links.newLeads).get('filter[status]')).toBeNull();
        expect(params(links.inspections).get('filter[status]')).toBeNull();
        expect(params(links.appointments).get('filter[status]')).toBeNull();
        expect(params(links.won).get('filter[status]')).toBe('won');
    });
});
