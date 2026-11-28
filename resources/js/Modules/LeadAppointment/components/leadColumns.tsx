/**
 * The lead list columns, shared by the Leads list screen and the dashboard's
 * embedded table panel. One vocabulary for a lead row: a stage tag, a source
 * label, a region and a created date, spelled the same on every surface.
 */
import { Tag } from '@/Components/ui/tag';
import type { SimpleColumn } from '@/Components/DataTable';
import { formatDateTime } from '@/lib/date';
import { humanize } from '@/lib/utils';

export type LeadRow = Modules.LeadAppointment.Application.Dtos.LeadListItemData;

export const statusVariant = (
    status: string,
): 'success' | 'warning' | 'destructive' | 'outline' => {
    if (status === 'won') {
        return 'success';
    }

    if (status === 'lost') {
        return 'destructive';
    }

    if (status === 'inspection_scheduled' || status === 'qualified') {
        return 'warning';
    }

    return 'outline';
};

export const leadColumns: SimpleColumn<LeadRow>[] = [
    {
        accessorKey: 'full_name',
        header: 'Lead',
        sortKey: 'first_name',
        hidable: false,
        className: 'min-w-[160px] max-w-[240px] sm:min-w-[180px]',
        cell: ({ row }): React.ReactNode => (
            <span className="flex min-w-0 flex-col">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-medium text-surface-900 dark:text-surface-0">
                        {row.full_name}
                    </span>
                    {row.is_emergency && <Tag variant="destructive">Emergency</Tag>}
                    {row.deleted_at !== null && <Tag variant="destructive">Deleted</Tag>}
                </span>
                <span className="truncate text-xs text-surface-500 sm:hidden dark:text-surface-300">
                    {row.phone ?? row.email ?? '—'}
                </span>
            </span>
        ),
    },
    {
        accessorKey: 'phone',
        header: 'Phone',
        sortable: false,
        className: 'whitespace-nowrap tabular-nums',
        hideBelow: 'sm',
        cell: ({ value }): React.ReactNode => (
            <span className="text-surface-600 dark:text-surface-300">
                {typeof value === 'string' && value !== '' ? value : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Stage',
        sortKey: 'status',
        className: 'whitespace-nowrap',
        cell: ({ value }): React.ReactNode =>
            typeof value === 'string' ? (
                <Tag variant={statusVariant(value)}>{humanize(value)}</Tag>
            ) : (
                '—'
            ),
    },
    {
        accessorKey: 'source',
        header: 'Source',
        sortKey: 'source',
        className: 'whitespace-nowrap',
        hideBelow: 'sm',
        cell: ({ value }): React.ReactNode =>
            typeof value === 'string' ? (
                <span className="text-surface-600 dark:text-surface-300">{humanize(value)}</span>
            ) : (
                '—'
            ),
    },
    {
        accessorKey: 'city',
        header: 'Region',
        sortable: false,
        className: 'max-w-[150px]',
        hideBelow: 'md',
        cell: ({ row }): React.ReactNode => (
            <span className="block truncate text-surface-600 dark:text-surface-300">
                {[row.city, row.state].filter(Boolean).join(', ') || '—'}
            </span>
        ),
    },
    {
        accessorKey: 'owner_name',
        header: 'Owner',
        sortable: false,
        className: 'max-w-[150px]',
        hideBelow: 'lg',
        cell: ({ value }): React.ReactNode => (
            <span className="block truncate text-surface-600 dark:text-surface-300">
                {typeof value === 'string' && value !== '' ? value : 'Unassigned'}
            </span>
        ),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        sortKey: 'created_at',
        align: 'right',
        width: 'w-38',
        className: 'whitespace-nowrap tabular-nums text-surface-600 dark:text-surface-300',
        hideBelow: 'xl',
        cell: ({ row }): React.ReactNode => formatDateTime(row.created_at),
    },
];
