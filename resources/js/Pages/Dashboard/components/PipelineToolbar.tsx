/**
 * The pipeline toolbar: view selector, period, scope filters and the one-line
 * pipeline summary. Extracted from the dashboard page so the page owns
 * orchestration and the toolbar owns its segmented-control styling.
 */
import { CalendarClock, KanbanSquare, Table2 } from 'lucide-react';
import CrudFilters, { type CrudFilterDef } from '@/Components/DataTable/CrudFilters';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import type { TableFilters, TableState } from '@/lib/table';
import { cn } from '@/lib/utils';
import type { DashboardView, Option } from '../types';

interface PipelineToolbarProps {
    view: DashboardView;
    onViewChange: (view: DashboardView) => void;
    canViewLeads: boolean;
    canViewCalendar: boolean;
    pipelineTotal: number;
    stageCount: number;
    rangeOptions: Option[];
    range: string;
    rangeLabel: string;
    onRangeChange: (range: string) => void;
    filterDefs: CrudFilterDef[];
    filterState: TableState;
    onFiltersChange: (patch: TableFilters) => void;
    disabled: boolean;
}

const segmentClass = (active: boolean): string =>
    cn(
        'inline-flex h-8 items-center justify-center gap-1.5 rounded-md px-3 text-xs font-semibold transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none',
        active
            ? 'bg-surface-0 text-surface-900 shadow-sm ring-1 ring-aq-content-line dark:bg-surface-800 dark:text-surface-0 dark:ring-aq-content-line'
            : 'text-surface-500 hover:bg-surface-0/70 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-surface-0',
    );

const segmentIconClass = (active: boolean): string =>
    cn(
        'size-3.5 shrink-0',
        active ? 'text-aq-accent-text' : 'text-surface-400 dark:text-surface-400',
    );

export default function PipelineToolbar({
    view,
    onViewChange,
    canViewLeads,
    canViewCalendar,
    pipelineTotal,
    stageCount,
    rangeOptions,
    range,
    rangeLabel,
    onRangeChange,
    filterDefs,
    filterState,
    onFiltersChange,
    disabled,
}: PipelineToolbarProps) {
    return (
        <div className="mb-3 flex flex-col gap-3 rounded-xl border border-aq-content-line p-2 xl:flex-row xl:items-center xl:justify-between">
            <div
                role="group"
                aria-label="Pipeline view"
                className="inline-flex self-start items-center gap-0.5 rounded-lg border border-aq-content-line bg-surface-100/70 p-1 dark:bg-surface-950/40"
            >
                {canViewLeads && (
                    <button
                        type="button"
                        aria-pressed={view === 'kanban'}
                        onClick={() => onViewChange('kanban')}
                        className={segmentClass(view === 'kanban')}
                    >
                        <KanbanSquare
                            className={segmentIconClass(view === 'kanban')}
                            aria-hidden="true"
                        />
                        Kanban
                    </button>
                )}

                {canViewLeads && (
                    <button
                        type="button"
                        aria-pressed={view === 'table'}
                        onClick={() => onViewChange('table')}
                        className={segmentClass(view === 'table')}
                    >
                        <Table2 className={segmentIconClass(view === 'table')} aria-hidden="true" />
                        Table
                    </button>
                )}

                {canViewCalendar && (
                    <button
                        type="button"
                        aria-pressed={view === 'calendar'}
                        onClick={() => onViewChange('calendar')}
                        className={segmentClass(view === 'calendar')}
                    >
                        <CalendarClock
                            className={segmentIconClass(view === 'calendar')}
                            aria-hidden="true"
                        />
                        Calendar
                    </button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {canViewLeads && pipelineTotal > 0 && (
                    <p className="text-xs text-surface-600 dark:text-surface-300">
                        <span className="font-semibold text-surface-700 tabular-nums dark:text-surface-200">
                            {pipelineTotal}
                        </span>{' '}
                        {pipelineTotal === 1 ? 'lead' : 'leads'} across {stageCount}{' '}
                        {stageCount === 1 ? 'stage' : 'stages'}
                    </p>
                )}

                <Select
                    items={rangeOptions}
                    value={range}
                    onValueChange={(next) => onRangeChange(String(next))}
                    disabled={disabled}
                >
                    <SelectTrigger className="w-40" aria-label="Metric period">
                        <SelectValue>{rangeLabel}</SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {rangeOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {canViewLeads && (
                    <CrudFilters
                        defs={filterDefs}
                        state={filterState}
                        onChange={onFiltersChange}
                        disabled={disabled}
                        className="w-auto"
                    />
                )}
            </div>
        </div>
    );
}
