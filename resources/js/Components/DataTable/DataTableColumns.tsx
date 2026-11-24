/**
 * DataTableColumns — column visibility and row density for DataTableKit.
 *
 * Visibility is a TanStack Table presentation slice (`columnVisibilityFeature`),
 * not a server filter: hiding a column must never trigger a visit or change the
 * export. The menu is a non-modal Base UI popover so the table stays readable
 * while the user builds a view.
 *
 * Two guards matter more than the checkboxes:
 *   - a locked column (`hidable: false`) stays visible;
 *   - the last visible data column cannot be hidden, so the table never
 *     collapses into an empty shell.
 */
import { useId } from 'react';
import { Columns3, Lock } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Popover, PopoverContent, PopoverTitle, PopoverTrigger } from '@/Components/ui/popover';
import { Switch } from '@/Components/ui/switch';
import type { RowData, ServerTable, SimpleColumn } from '@/lib/data-table';
import type { TableDensity } from '@/lib/table-preferences';
import { cn } from '@/lib/utils';

interface DataTableColumnsProps<TData extends RowData> {
    table: ServerTable<TData>;
    columns: readonly SimpleColumn<TData>[];
    density: TableDensity;
    onDensityChange: (density: TableDensity) => void;
}

export default function DataTableColumns<TData extends RowData>({
    table,
    columns,
    density,
    onDensityChange,
}: DataTableColumnsProps<TData>) {
    const densityId = useId();
    const leafColumns = table.getAllLeafColumns();
    const visibleCount = table.getVisibleLeafColumns().length;
    const hiddenCount = leafColumns.filter((column) => !column.getIsVisible()).length;
    const isDefault = hiddenCount === 0 && density === 'comfortable';

    const labelFor = (columnId: string): string => {
        const column = columns.find((entry) => (entry.id ?? entry.accessorKey) === columnId);

        return column?.header ?? columnId;
    };

    const reset = (): void => {
        // `resetColumnVisibility()` restores the *initial* state, which is the
        // stored preference. "Reset to defaults" must mean all columns visible.
        table.setColumnVisibility({});
        onDensityChange('comfortable');
    };

    return (
        <Popover>
            <PopoverTrigger render={<Button type="button" variant="outline" size="toolbar" />}>
                <Columns3
                    className="size-3.5 text-surface-500 dark:text-surface-400"
                    aria-hidden="true"
                />
                Columns
                {hiddenCount > 0 ? (
                    <span className="rounded-full bg-surface-100 px-1.5 py-0.5 text-[11px] font-semibold text-surface-600 tabular-nums dark:bg-surface-800 dark:text-surface-300">
                        {hiddenCount}
                    </span>
                ) : null}
            </PopoverTrigger>

            <PopoverContent align="end" className="w-72 gap-0 p-2">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <PopoverTitle className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        Columns
                    </PopoverTitle>
                    <span className="text-[11px] text-surface-500 tabular-nums dark:text-surface-400">
                        {visibleCount} of {leafColumns.length}
                    </span>
                </div>

                <div className="flex flex-col gap-0.5">
                    {leafColumns.map((column) => {
                        const label = labelFor(column.id);
                        const canHide = column.getCanHide();
                        const visible = column.getIsVisible();
                        const locked = !canHide;
                        const lastVisible = visible && visibleCount <= 1;
                        const disabled = locked || lastVisible;

                        return (
                            <div
                                key={column.id}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-md px-2 py-1.5',
                                    disabled && 'opacity-60',
                                )}
                                title={
                                    locked
                                        ? 'This column is always visible'
                                        : lastVisible
                                          ? 'Keep at least one column visible'
                                          : undefined
                                }
                            >
                                <Checkbox
                                    checked={visible}
                                    disabled={disabled}
                                    onCheckedChange={(checked) => column.toggleVisibility(checked)}
                                    aria-label={label}
                                />
                                <span className="min-w-0 flex-1 truncate text-sm text-surface-700 dark:text-surface-200">
                                    {label}
                                </span>
                                {locked ? (
                                    <Lock
                                        className="size-3.5 shrink-0 text-surface-400"
                                        aria-hidden="true"
                                    />
                                ) : null}
                            </div>
                        );
                    })}
                </div>

                <div className="mt-1 border-t border-aq-shell-line pt-2">
                    <div className="flex items-center justify-between gap-3 rounded-md px-2 py-1.5">
                        <label
                            htmlFor={densityId}
                            className="text-sm text-surface-700 dark:text-surface-200"
                        >
                            Compact rows
                        </label>
                        <Switch
                            id={densityId}
                            size="sm"
                            checked={density === 'compact'}
                            onCheckedChange={(checked) =>
                                onDensityChange(checked ? 'compact' : 'comfortable')
                            }
                        />
                    </div>
                </div>

                <div className="mt-1 border-t border-aq-shell-line pt-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 w-full justify-start px-2 text-xs"
                        onClick={reset}
                        disabled={isDefault}
                    >
                        Reset to defaults
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
