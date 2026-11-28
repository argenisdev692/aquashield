/**
 * Dashboard KPI destinations.
 *
 * Every KPI is computed over the selected period (`rangeFrom`–`rangeTo`).
 * The destination list must carry the same window, otherwise the number and
 * the rows it opens describe two different populations.
 */
import { buildTableUrl, type TableState } from '@/lib/table';

export interface DashboardMetricLinks {
    newLeads: string;
    inspections: string;
    won: string;
    appointments: string;
}

const metricState = (filters: TableState['filters']): TableState => ({
    page: 1,
    perPage: 15,
    search: '',
    sort: null,
    filters,
});

export const dashboardMetricLinks = (rangeFrom: string, rangeTo: string): DashboardMetricLinks => {
    const window = { date_from: rangeFrom, date_to: rangeTo };

    return {
        newLeads: buildTableUrl('/leads', metricState({ ...window }), { paginate: false }),
        inspections: buildTableUrl(
            '/appointments',
            metricState({ ...window, kind: 'inspection' }),
            { paginate: false },
        ),
        won: buildTableUrl('/leads', metricState({ ...window, status: 'won' }), {
            paginate: false,
        }),
        appointments: buildTableUrl('/appointments', metricState({ ...window }), {
            paginate: false,
        }),
    };
};
