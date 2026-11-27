/**
 * The dashboard's embedded leads table: a client-owned panel, not a page visit.
 *
 * `/leads` already answers JSON for the DataTable kit, so the panel reads the
 * same server-first endpoint with the same `lib/table` query contract. Page,
 * search and sort live in the panel; region and source are dashboard scope
 * filters, so the board, the KPIs and the table agree on one scope, while the
 * stage filter only narrows this table.
 */
import { keepPreviousData, queryOptions, useQuery } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';

export type LeadPanelRow = Modules.LeadAppointment.Application.Dtos.LeadListItemData;

export interface LeadsPanelPage {
    rows: LeadPanelRow[];
    total: number;
    page: number;
    perPage: number;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isLeadRow = (value: unknown): value is LeadPanelRow =>
    isRecord(value) &&
    typeof value.uuid === 'string' &&
    typeof value.full_name === 'string' &&
    typeof value.status === 'string' &&
    typeof value.source === 'string' &&
    (value.deleted_at === null || typeof value.deleted_at === 'string');

const fetchLeadsPanel = async (
    params: URLSearchParams,
    signal: AbortSignal,
): Promise<LeadsPanelPage> => {
    const payload = await requestJson(`/leads?${params.toString()}`, { signal });

    if (
        !isRecord(payload) ||
        !Array.isArray(payload.rows) ||
        typeof payload.total !== 'number' ||
        typeof payload.page !== 'number' ||
        typeof payload.per_page !== 'number'
    ) {
        throw new Error('The leads panel returned an unexpected payload.');
    }

    const rows: LeadPanelRow[] = [];

    for (const row of payload.rows) {
        if (!isLeadRow(row)) {
            throw new Error('The leads panel returned a row this client cannot read.');
        }

        rows.push(row);
    }

    return { rows, total: payload.total, page: payload.page, perPage: payload.per_page };
};

export const leadsPanelQuery = (params: URLSearchParams, enabled = true) =>
    queryOptions({
        queryKey: ['lead-appointment', 'leads-panel', params.toString()],
        enabled,
        queryFn: ({ signal }) => fetchLeadsPanel(params, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: keepPreviousData,
    });

export interface LeadsPanelController {
    rows: LeadPanelRow[];
    total: number;
    isPending: boolean;
    isFetching: boolean;
    isError: boolean;
    retry: () => void;
}

export function useLeadsPanel(params: URLSearchParams, enabled = true): LeadsPanelController {
    const query = useQuery(leadsPanelQuery(params, enabled));

    return {
        rows: query.data?.rows ?? [],
        total: query.data?.total ?? 0,
        isPending: query.isPending,
        isFetching: query.isFetching,
        isError: query.isError,
        retry: () => {
            void query.refetch();
        },
    };
}
