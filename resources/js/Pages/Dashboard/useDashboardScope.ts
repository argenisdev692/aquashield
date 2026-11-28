/**
 * The dashboard's URL scope: period + region/source filters.
 *
 * A scope change is an Inertia visit, so the URL updates only after the
 * response. Reading the current URL for every commit loses a change made while
 * the previous visit is still in flight. `desired` is the synchronous view of
 * what the next response should contain; `useCrudTable` uses the same pattern
 * for list screens. Keeping it here makes the race testable without mounting
 * the whole dashboard.
 */
import { useEffect, useRef, useState } from 'react';
import { isBlankFilter, parseTableParams, type TableFilters } from '@/lib/table';

export const DEFAULT_RANGE = 'last_30_days';

export interface ScopeVisitOptions {
    onFinish: () => void;
    onCancel: () => void;
}

export type ScopeVisit = (url: string, options: ScopeVisitOptions) => void;

export interface DashboardScope {
    filters: TableFilters;
    /** The period shown by the select: the pending choice while a visit runs. */
    range: string;
    pending: boolean;
    commit: (nextRange: string, patch: TableFilters) => void;
}

interface ScopeSnapshot {
    range: string;
    filters: TableFilters;
}

export const mergeScopeFilters = (base: TableFilters, patch: TableFilters): TableFilters => {
    const filters: TableFilters = { ...base };

    for (const [key, value] of Object.entries(patch)) {
        if (isBlankFilter(value)) {
            delete filters[key];

            continue;
        }

        filters[key] = value;
    }

    return filters;
};

/** Period + scope filters are the dashboard URL; everything else is local. */
export const dashboardUrl = (range: string, filters: TableFilters): string => {
    const params = new URLSearchParams();

    if (range !== DEFAULT_RANGE) {
        params.set('range', range);
    }

    for (const [key, value] of Object.entries(filters)) {
        if (!isBlankFilter(value)) {
            params.set(`filter[${key}]`, String(value));
        }
    }

    const query = params.toString();

    return query === '' ? '/dashboard' : `/dashboard?${query}`;
};

export function useDashboardScope({
    url,
    range,
    visit,
}: {
    url: string;
    range: string;
    visit: ScopeVisit;
}): DashboardScope {
    const urlFilters = parseTableParams(url).filters;
    const [desired, setDesired] = useState<ScopeSnapshot | null>(null);
    const desiredRef = useRef<ScopeSnapshot | null>(null);
    const pendingCount = useRef(0);
    const [pending, setPending] = useState(false);

    const filters = desired?.filters ?? urlFilters;
    const currentRange = desired?.range ?? range;

    /*
     * A back/forward navigation (or another component's visit) replaces the
     * URL without passing through `settle`. Drop a leftover desired snapshot
     * only when no visit this hook started is still in flight.
     */
    useEffect(() => {
        if (pendingCount.current === 0 && desiredRef.current !== null) {
            desiredRef.current = null;
            setDesired(null);
            setPending(false);
        }
    }, [url]);

    const commit = (nextRange: string, patch: TableFilters): void => {
        const base = desiredRef.current?.filters ?? urlFilters;
        const next: ScopeSnapshot = {
            range: nextRange,
            filters: mergeScopeFilters(base, patch),
        };

        desiredRef.current = next;
        setDesired(next);
        pendingCount.current += 1;
        setPending(true);

        let settled = false;

        const settle = (): void => {
            if (settled) {
                return;
            }

            settled = true;
            pendingCount.current = Math.max(0, pendingCount.current - 1);

            if (pendingCount.current === 0) {
                desiredRef.current = null;
                setDesired(null);
                setPending(false);
            }
        };

        visit(dashboardUrl(next.range, next.filters), {
            onFinish: settle,
            onCancel: settle,
        });
    };

    return { filters, range: currentRange, pending, commit };
}
