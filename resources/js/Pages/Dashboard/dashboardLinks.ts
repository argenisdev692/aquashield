/**
 * Dashboard KPI destinations.
 *
 * Every KPI is computed over the selected period (`rangeFrom`–`rangeTo`) and
 * the dashboard's region/source scope. The destination list must carry both,
 * otherwise the number and the rows it opens describe two different
 * populations.
 *
 * The metric-specific window is part of the contract:
 * - new leads and appointments are filtered by their own date window;
 * - won is filtered by `converted_from`/`converted_to`, because the KPI counts
 *   `converted_at` rather than `created_at`;
 * - inspections and appointments exclude cancelled/no-show visits, matching
 *   `countScheduledBetween()`.
 */
import { buildTableUrl, isBlankFilter, type TableFilters, type TableState } from '@/lib/table';

export interface DashboardMetricLinks {
    newLeads: string;
    inspections: string;
    won: string;
    appointments: string;
}

const metricState = (filters: TableFilters): TableState => ({
    page: 1,
    perPage: 15,
    search: '',
    sort: null,
    filters,
});

/**
 * Only the dashboard scope filters that also scope the KPI counters. The table
 * pane can add its own status filter to `scope.filters`; it must not leak into
 * the KPI destinations.
 */
const boardScope = (filters: TableFilters): TableFilters => {
    const scope: TableFilters = {};

    if (!isBlankFilter(filters.region)) {
        scope.region = filters.region;
    }

    if (!isBlankFilter(filters.source)) {
        scope.source = filters.source;
    }

    return scope;
};

export const dashboardMetricLinks = (
    rangeFrom: string,
    rangeTo: string,
    filters: TableFilters = {},
): DashboardMetricLinks => {
    const window = { date_from: rangeFrom, date_to: rangeTo };
    const scope = boardScope(filters);

    return {
        newLeads: buildTableUrl('/leads', metricState({ ...scope, ...window }), {
            paginate: false,
        }),
        inspections: buildTableUrl(
            '/appointments',
            metricState({ ...scope, ...window, kind: 'inspection', scheduled_only: '1' }),
            { paginate: false },
        ),
        won: buildTableUrl(
            '/leads',
            metricState({
                ...scope,
                converted_from: rangeFrom,
                converted_to: rangeTo,
                status: 'won',
            }),
            { paginate: false },
        ),
        appointments: buildTableUrl(
            '/appointments',
            metricState({ ...scope, ...window, scheduled_only: '1' }),
            { paginate: false },
        ),
    };
};
