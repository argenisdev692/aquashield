/**
 * Lead options for the appointment picker.
 *
 * A lead list can be long, so the server owns the search and TanStack Query
 * owns the small result set. The component never calls fetch: it passes the
 * debounced term and renders what this hook returns.
 */
import { keepPreviousData, queryOptions, useQuery } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';

export type LeadOption = Pick<
    Modules.LeadAppointment.Application.Dtos.LeadListItemData,
    'uuid' | 'full_name' | 'phone' | 'city' | 'state' | 'region'
>;

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isLeadOption = (value: unknown): value is LeadOption =>
    isRecord(value) &&
    typeof value.uuid === 'string' &&
    typeof value.full_name === 'string' &&
    (value.phone === null || typeof value.phone === 'string') &&
    (value.city === null || typeof value.city === 'string') &&
    (value.state === null || typeof value.state === 'string') &&
    (value.region === null || typeof value.region === 'string');

export const leadOptionsQuery = (search: string, enabled = true) =>
    queryOptions({
        queryKey: ['lead-appointment', 'lead-options', search] as const,
        enabled,
        queryFn: async ({ signal }): Promise<LeadOption[]> => {
            const params = new URLSearchParams({ per_page: '15', sort: '-created_at' });

            if (search.trim() !== '') {
                params.set('search', search.trim());
            }

            const payload = await requestJson(`/leads?${params.toString()}`, { signal });

            if (!isRecord(payload) || !Array.isArray(payload.rows)) {
                throw new Error('The lead search returned an unexpected payload.');
            }

            const leads: LeadOption[] = [];

            for (const row of payload.rows) {
                if (!isLeadOption(row)) {
                    throw new Error('The lead search returned a row this client cannot read.');
                }

                leads.push(row);
            }

            return leads;
        },
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: keepPreviousData,
    });

export interface LeadOptionsController {
    leads: LeadOption[];
    isPending: boolean;
    isFetching: boolean;
    isError: boolean;
}

export function useLeadOptions(search: string, enabled = true): LeadOptionsController {
    const query = useQuery(leadOptionsQuery(search, enabled));

    return {
        leads: query.data ?? [],
        isPending: query.isPending,
        isFetching: query.isFetching,
        isError: query.isError,
    };
}
