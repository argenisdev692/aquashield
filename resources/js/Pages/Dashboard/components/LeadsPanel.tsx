/**
 * LeadsPanel — the dashboard's Table view.
 *
 * A client-owned panel on the shared server-first kit: page, search and sort
 * are local panel state. Region and source are dashboard scope filters, so
 * they live in the URL and the board, the KPI row and this table agree on one
 * scope; the stage filter is table-only but still rides the URL, so reload and
 * back/forward keep the view the user left. The period arrives as bounds from
 * the server and rides the request without appearing in the URL or the filter
 * badge. The query hook owns the transport; this component owns only
 * presentation state.
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import {
    CrudFilters,
    DataTableKit,
    DataTableRowActions,
    type CrudFilterDef,
} from '@/Components/DataTable';
import {
    buildTableParams,
    countActiveFilters,
    type TableFilters,
    type TableState,
} from '@/lib/table';
import { useLeadsPanel } from '@/Modules/LeadAppointment/hooks/useLeadsPanel';
import { leadColumns, type LeadRow } from '@/Modules/LeadAppointment/components/leadColumns';

type Option = { value: string; label: string };

interface LeadsPanelProps {
    options: {
        statuses: Option[];
        sources: Option[];
        regions: Option[];
    };
    /** Scope filters owned by the dashboard URL. */
    filters: TableFilters;
    /** The selected period's inclusive bounds; the table reads the same window. */
    rangeFrom: string;
    rangeTo: string;
    onFiltersChange: (patch: TableFilters) => void;
    canView: boolean;
    canUpdate: boolean;
    /** Hidden Activity panes must not fetch. */
    enabled: boolean;
}

export default function LeadsPanel({
    options,
    filters,
    rangeFrom,
    rangeTo,
    onFiltersChange,
    canView,
    canUpdate,
    enabled,
}: LeadsPanelProps) {
    const [local, setLocal] = useState<Omit<TableState, 'filters'>>({
        page: 1,
        perPage: 15,
        search: '',
        sort: { field: 'created_at', order: -1 },
    });

    const state: TableState = { ...local, filters };

    // The period is part of the dashboard scope, not a user filter: it rides
    // in the request but never in the URL or the filter badge.
    const panel = useLeadsPanel(
        buildTableParams({
            ...state,
            filters: { ...filters, date_from: rangeFrom, date_to: rangeTo },
        }),
        enabled,
    );
    const defs: CrudFilterDef[] = [
        {
            key: 'status',
            label: 'Stage',
            type: 'select',
            placeholder: 'Any stage',
            options: options.statuses,
        },
        {
            key: 'source',
            label: 'Source',
            type: 'select',
            placeholder: 'Any source',
            options: options.sources,
        },
        {
            key: 'region',
            label: 'Region',
            type: 'select',
            placeholder: 'Any region',
            options: options.regions,
        },
    ];

    const activeFilterCount = countActiveFilters(filters);

    return (
        <DataTableKit
            columns={leadColumns}
            data={panel.rows}
            total={panel.total}
            page={local.page}
            setPage={(page) => setLocal((current) => ({ ...current, page: Math.max(1, page) }))}
            perPage={local.perPage}
            setPerPage={(perPage) => setLocal((current) => ({ ...current, perPage, page: 1 }))}
            search={local.search}
            setSearch={(search) => setLocal((current) => ({ ...current, search, page: 1 }))}
            sort={
                local.sort === null
                    ? null
                    : {
                          field: local.sort.field,
                          direction: local.sort.order === -1 ? 'desc' : 'asc',
                      }
            }
            setSort={(sort) =>
                setLocal((current) => ({
                    ...current,
                    page: 1,
                    sort:
                        sort === null
                            ? null
                            : { field: sort.field, order: sort.direction === 'desc' ? -1 : 1 },
                }))
            }
            loading={panel.isPending}
            error={panel.isError ? 'The leads table could not be loaded.' : undefined}
            onRetry={panel.retry}
            title="All leads"
            description="The full filtered list for this period."
            searchPlaceholder="Search name, company, email, phone, address or city…"
            filterCount={activeFilterCount}
            hasActiveFilters={activeFilterCount > 0 || local.search.trim() !== ''}
            filters={
                <CrudFilters
                    defs={defs}
                    state={state}
                    onChange={onFiltersChange}
                    disabled={panel.isFetching}
                />
            }
            onResetFilters={() => onFiltersChange({ status: null, source: null, region: null })}
            exportFormats={[]}
            rowState={(row) => (row.deleted_at !== null ? 'deleted' : 'default')}
            rowActions={(row: LeadRow) => (
                <DataTableRowActions
                    label={`Actions for ${row.full_name}`}
                    actions={[
                        ...(canView
                            ? [
                                  {
                                      label: 'View',
                                      icon: <Eye className="size-4" aria-hidden="true" />,
                                      onClick: () => router.visit(`/leads/${row.uuid}`),
                                  },
                              ]
                            : []),
                        ...(canUpdate && row.deleted_at === null
                            ? [
                                  {
                                      label: 'Edit',
                                      icon: <Pencil className="size-4" aria-hidden="true" />,
                                      onClick: () => router.visit(`/leads/${row.uuid}/edit`),
                                  },
                              ]
                            : []),
                    ]}
                />
            )}
            emptyTitle="No leads match this view"
            emptyDescription="Adjust the filters, or create a lead to start the pipeline."
        />
    );
}
