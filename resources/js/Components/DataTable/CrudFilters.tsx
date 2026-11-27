/**
 * CrudFilters — one config-driven filter panel for every list screen.
 *
 * The panel is a draft, not a second source of truth. It is seeded from the
 * URL when it opens, edited locally, and committed as one `setFilters` call on
 * Apply. That batching matters on a server-first table: binding a select
 * directly to `state.filters` makes it snap back to the previous value for the
 * whole Inertia round-trip, and three changed fields would fire three visits.
 *
 * The applied state never disappears behind the panel. A count badge sits on
 * the trigger and every active filter renders as a removable chip, because a
 * filtered table that looks identical to an unfiltered one is how people
 * misread data.
 *
 * Text filters keep the table convention: two characters minimum, blank means
 * "remove the key", and a date range repairs the opposite bound instead of
 * submitting an invalid range.
 */
import { useId, useState } from 'react';
import { Calendar, Check, Filter, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Input } from '@/Components/ui/input';
import { Popover, PopoverContent, PopoverTitle, PopoverTrigger } from '@/Components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { formatIsoDate, todayIso, toIsoDate } from '@/lib/date';
import { OPTION_PREVIEW_LIMIT, previewOptions } from '@/lib/options';
import type { TableFilters, TableFilterValue, TableState } from '@/lib/table';
import { cn } from '@/lib/utils';

const MIN_TEXT_LENGTH = 2;
const ALL_VALUE = '__all__';

export type CrudFilterDef =
    | {
          key: string;
          label: string;
          type: 'text';
          placeholder?: string;
      }
    | {
          key: string;
          label: string;
          type: 'select';
          placeholder?: string;
          options: readonly { value: string; label: string }[];
          /**
           * Use a searchable list instead of a select. Defaults to true when
           * the option set is larger than 40; a 500-item native-like select is
           * a scrollbar, not a choice.
           */
          searchable?: boolean;
      }
    | {
          key: string;
          label: string;
          type: 'multi-select';
          placeholder?: string;
          options: readonly { value: string; label: string }[];
          /** Always rendered as a searchable checklist. */
          searchable?: boolean;
      }
    | {
          key: string;
          label: string;
          type: 'date-range';
          fromKey?: string;
          toKey?: string;
          presets?: boolean;
          /** Timezone used to build calendar presets, e.g. `UTC`. */
          timeZone?: string;
      };

interface CrudFiltersProps {
    defs: readonly CrudFilterDef[];
    state: TableState;
    onChange: (patch: TableFilters) => void;
    disabled?: boolean;
    /**
     * Extra classes for the bar. The default is full width, which suits the
     * table toolbar; an inline toolbar (the dashboard) passes `w-auto` so the
     * trigger sits on the same row as the period select.
     */
    className?: string;
}

const labelClass = 'text-xs font-medium text-surface-600 dark:text-surface-300';

const asString = (value: TableFilterValue): string => (typeof value === 'string' ? value : '');

const asList = (value: TableFilterValue): string[] => {
    if (Array.isArray(value)) {
        return value.map((item) => String(item));
    }

    if (typeof value === 'string' && value !== '') {
        return [value];
    }

    return [];
};

const dateKeys = (
    def: Extract<CrudFilterDef, { type: 'date-range' }>,
): { fromKey: string; toKey: string } => ({
    fromKey: def.fromKey ?? 'date_from',
    toKey: def.toKey ?? 'date_to',
});

const countActiveDefs = (defs: readonly CrudFilterDef[], filters: TableFilters): number =>
    defs.filter((def) => {
        if (def.type === 'date-range') {
            const { fromKey, toKey } = dateKeys(def);

            return asString(filters[fromKey]) !== '' || asString(filters[toKey]) !== '';
        }

        if (def.type === 'multi-select') {
            return asList(filters[def.key]).length > 0;
        }

        return asString(filters[def.key]) !== '';
    }).length;

/**
 * Build a full patch over every declared filter, not just the changed ones.
 * `setFilters` merges; omitting a cleared key would leave the old value in the
 * URL, so a cleared field is sent as `null` explicitly.
 */
const buildPatch = (defs: readonly CrudFilterDef[], source: TableFilters): TableFilters => {
    const patch: TableFilters = {};

    for (const def of defs) {
        if (def.type === 'date-range') {
            const { fromKey, toKey } = dateKeys(def);
            patch[fromKey] = asString(source[fromKey]) || null;
            patch[toKey] = asString(source[toKey]) || null;

            continue;
        }

        const value = asString(source[def.key]).trim();

        if (def.type === 'multi-select') {
            const list = asList(source[def.key]);
            patch[def.key] = list.length > 0 ? list : null;

            continue;
        }

        patch[def.key] =
            def.type === 'text'
                ? value.length >= MIN_TEXT_LENGTH
                    ? value
                    : null
                : value !== ''
                  ? value
                  : null;
    }

    return patch;
};

const blankPatch = (defs: readonly CrudFilterDef[]): TableFilters => {
    const patch: TableFilters = {};

    for (const def of defs) {
        if (def.type === 'date-range') {
            const { fromKey, toKey } = dateKeys(def);
            patch[fromKey] = null;
            patch[toKey] = null;

            continue;
        }

        patch[def.key] = null;
    }

    return patch;
};

const formatChipDate = (value: string): string => {
    const formatted = formatIsoDate(value, 'en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

    return formatted === '' ? value : formatted;
};

interface ActiveFilterChip {
    id: string;
    label: string;
    onRemove: () => void;
}

const buildChips = (
    defs: readonly CrudFilterDef[],
    filters: TableFilters,
    onChange: (patch: TableFilters) => void,
): ActiveFilterChip[] => {
    const chips: ActiveFilterChip[] = [];

    for (const def of defs) {
        if (def.type === 'date-range') {
            const { fromKey, toKey } = dateKeys(def);
            const from = asString(filters[fromKey]);
            const to = asString(filters[toKey]);

            if (from === '' && to === '') {
                continue;
            }

            const range =
                from !== '' && to !== ''
                    ? `${formatChipDate(from)} – ${formatChipDate(to)}`
                    : from !== ''
                      ? `From ${formatChipDate(from)}`
                      : `Until ${formatChipDate(to)}`;

            chips.push({
                id: def.key,
                label: `${def.label}: ${range}`,
                onRemove: () => onChange({ [fromKey]: null, [toKey]: null }),
            });

            continue;
        }

        const value = asString(filters[def.key]);

        if (def.type === 'multi-select') {
            const selected = asList(filters[def.key]);

            for (const entry of selected) {
                const label = def.options.find((option) => option.value === entry)?.label ?? entry;

                chips.push({
                    id: `${def.key}-${entry}`,
                    label: `${def.label}: ${label}`,
                    onRemove: () =>
                        onChange({
                            [def.key]: selected.filter((candidate) => candidate !== entry),
                        }),
                });
            }

            continue;
        }

        if (value === '') {
            continue;
        }

        const option =
            def.type === 'select'
                ? def.options.find((entry) => entry.value === value)?.label
                : undefined;
        const label = option ?? value;

        chips.push({
            id: def.key,
            label: `${def.label}: ${label}`,
            onRemove: () => onChange({ [def.key]: null }),
        });
    }

    return chips;
};

function TextFilter({
    def,
    value,
    onChange,
    disabled,
}: {
    def: Extract<CrudFilterDef, { type: 'text' }>;
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
}) {
    const uid = useId();
    const id = `filter-${def.key}-${uid}`;

    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className={labelClass}>
                {def.label}
            </label>
            <div className="relative">
                <Input
                    id={id}
                    name={def.key}
                    type="text"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={def.placeholder}
                    disabled={disabled}
                    className="h-9 w-full pr-8 text-sm dark:bg-surface-900"
                />
                {value !== '' ? (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        disabled={disabled}
                        aria-label={`Clear ${def.label}`}
                        className="absolute top-1/2 right-1.5 grid size-6 -translate-y-1/2 place-items-center rounded-md text-surface-500 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-800 motion-reduce:transition-none dark:hover:bg-surface-800 dark:hover:text-surface-100"
                    >
                        <X className="size-3.5" aria-hidden="true" />
                    </button>
                ) : null}
            </div>
        </div>
    );
}

function SelectFilter({
    def,
    value,
    onChange,
    disabled,
}: {
    def: Extract<CrudFilterDef, { type: 'select' }>;
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
}) {
    const uid = useId();
    const id = `filter-${def.key}-${uid}`;
    const current = value !== '' ? value : ALL_VALUE;
    const allLabel = def.placeholder ?? 'All';
    const items = [{ value: ALL_VALUE, label: allLabel }, ...def.options];
    const currentLabel =
        items.find((item) => item.value === current)?.label ?? (value !== '' ? value : allLabel);

    return (
        <div className="flex flex-col gap-1.5">
            <span id={id} className={labelClass}>
                {def.label}
            </span>
            <Select
                items={items}
                value={current}
                onValueChange={(next) => onChange(next === ALL_VALUE ? '' : String(next))}
                disabled={disabled}
            >
                <SelectTrigger className="h-9 w-full" aria-labelledby={id}>
                    <SelectValue placeholder={allLabel}>{currentLabel}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL_VALUE}>{allLabel}</SelectItem>
                    {def.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function SearchableSelectFilter({
    def,
    value,
    onChange,
    disabled,
}: {
    def: Extract<CrudFilterDef, { type: 'select' }>;
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
}) {
    const selected = def.options.find((option) => option.value === value);
    const allLabel = def.placeholder ?? 'All';
    const [query, setQuery] = useState('');
    const preview = previewOptions(def.options, value === '' ? [] : [value], query);

    return (
        <div className="flex flex-col gap-1.5">
            <span className={labelClass}>{def.label}</span>

            <div className="flex items-center justify-between gap-2 rounded-lg border border-surface-300 bg-surface-0 px-3 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-900">
                <span
                    className={cn(
                        'truncate',
                        selected
                            ? 'text-surface-700 dark:text-surface-100'
                            : 'text-surface-500 dark:text-surface-300',
                    )}
                >
                    {selected?.label ?? allLabel}
                </span>
                {value !== '' ? (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        disabled={disabled}
                        aria-label={`Clear ${def.label}`}
                        className="grid size-6 shrink-0 place-items-center rounded-md text-surface-500 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-800 motion-reduce:transition-none dark:hover:bg-surface-800 dark:hover:text-surface-100"
                    >
                        <X className="size-3.5" aria-hidden="true" />
                    </button>
                ) : null}
            </div>

            <Command
                aria-label={def.label}
                className={cn(
                    'h-auto w-full rounded-lg border border-surface-200 dark:border-surface-700',
                    disabled && 'pointer-events-none opacity-60',
                )}
            >
                <CommandInput
                    placeholder={`Search ${def.label.toLowerCase()}…`}
                    aria-label={`Search ${def.label}`}
                    value={query}
                    onValueChange={setQuery}
                    disabled={disabled}
                />
                <CommandList className="max-h-40">
                    <CommandEmpty>No matches.</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            value={allLabel}
                            data-checked={value === ''}
                            onSelect={() => onChange('')}
                        >
                            {allLabel}
                        </CommandItem>
                        {preview.options.map((option) => (
                            <CommandItem
                                key={option.value}
                                value={option.value}
                                data-checked={value === option.value}
                                onSelect={() => onChange(option.value)}
                            >
                                {option.label}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                    {preview.truncated ? (
                        <p className="px-2 py-1.5 text-xs text-surface-500 dark:text-surface-300">
                            Showing {OPTION_PREVIEW_LIMIT} of {def.options.length}. Search to
                            narrow.
                        </p>
                    ) : null}
                </CommandList>
            </Command>
        </div>
    );
}

function MultiSelectFilter({
    def,
    value,
    onChange,
    disabled,
}: {
    def: Extract<CrudFilterDef, { type: 'multi-select' }>;
    value: string[];
    onChange: (value: string[]) => void;
    disabled: boolean;
}) {
    const uid = useId();
    const id = `filter-${def.key}-${uid}`;
    const allLabel = def.placeholder ?? 'Any';
    const toggle = (entry: string): void => {
        onChange(
            value.includes(entry)
                ? value.filter((candidate) => candidate !== entry)
                : [...value, entry],
        );
    };

    return (
        <div className="flex flex-col gap-1.5">
            <span id={id} className={labelClass}>
                {def.label}
            </span>

            <Command
                aria-labelledby={id}
                className={cn(
                    'h-auto w-full rounded-lg border border-surface-200 dark:border-surface-700',
                    disabled && 'pointer-events-none opacity-60',
                )}
            >
                <CommandInput
                    placeholder={`Search ${def.label.toLowerCase()}…`}
                    aria-label={`Search ${def.label}`}
                    disabled={disabled}
                />
                <CommandList className="max-h-40">
                    <CommandEmpty>No matches.</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            value={allLabel}
                            data-checked={value.length === 0}
                            onSelect={() => onChange([])}
                        >
                            {value.length === 0 ? (
                                <Check className="size-3.5" aria-hidden="true" />
                            ) : (
                                <span className="size-3.5" aria-hidden="true" />
                            )}
                            {allLabel}
                        </CommandItem>
                        {def.options.map((option) => {
                            const selected = value.includes(option.value);

                            return (
                                <CommandItem
                                    key={option.value}
                                    value={option.value}
                                    data-checked={selected}
                                    onSelect={() => toggle(option.value)}
                                >
                                    {selected ? (
                                        <Check className="size-3.5" aria-hidden="true" />
                                    ) : (
                                        <span className="size-3.5" aria-hidden="true" />
                                    )}
                                    {option.label}
                                </CommandItem>
                            );
                        })}
                    </CommandGroup>
                </CommandList>
            </Command>

            {value.length > 0 ? (
                <button
                    type="button"
                    onClick={() => onChange([])}
                    disabled={disabled}
                    className="self-start text-xs font-medium text-surface-500 transition-colors duration-150 hover:text-surface-800 motion-reduce:transition-none dark:text-surface-300 dark:hover:text-surface-100"
                >
                    Clear {def.label.toLowerCase()}
                </button>
            ) : null}
        </div>
    );
}

type DatePreset = 'last7' | 'last30' | 'month' | 'year' | 'all';

const PRESETS: readonly { value: DatePreset; label: string }[] = [
    { value: 'last7', label: 'Last 7 days' },
    { value: 'last30', label: 'Last 30 days' },
    { value: 'month', label: 'This month' },
    { value: 'year', label: 'This year' },
    { value: 'all', label: 'All time' },
];

export const presetRange = (
    preset: DatePreset,
    timeZone?: string,
): { from: string | null; to: string | null } => {
    if (timeZone !== undefined) {
        const today = todayIso(timeZone);
        const [year, month] = today.split('-').map(Number);

        switch (preset) {
            case 'last7':
            case 'last30': {
                const start = new Date(`${today}T00:00:00Z`);
                start.setUTCDate(start.getUTCDate() - (preset === 'last7' ? 6 : 29));

                return { from: start.toISOString().slice(0, 10), to: today };
            }

            case 'month':
                return {
                    from: `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-01`,
                    to: today,
                };

            case 'year':
                return { from: `${String(year).padStart(4, '0')}-01-01`, to: today };

            default:
                return { from: null, to: null };
        }
    }

    const today = new Date();

    switch (preset) {
        case 'last7':
        case 'last30': {
            const start = new Date(today);
            start.setDate(start.getDate() - (preset === 'last7' ? 6 : 29));

            return { from: toIsoDate(start), to: toIsoDate(today) };
        }

        case 'month':
            return {
                from: toIsoDate(new Date(today.getFullYear(), today.getMonth(), 1)),
                to: toIsoDate(today),
            };

        case 'year':
            return {
                from: toIsoDate(new Date(today.getFullYear(), 0, 1)),
                to: toIsoDate(today),
            };

        default:
            return { from: null, to: null };
    }
};

function DateRangeFilter({
    def,
    valueFrom,
    valueTo,
    onChange,
    disabled,
}: {
    def: Extract<CrudFilterDef, { type: 'date-range' }>;
    valueFrom: string;
    valueTo: string;
    onChange: (patch: TableFilters) => void;
    disabled: boolean;
}) {
    const uid = useId();
    const groupId = `filter-date-group-${uid}`;
    const { fromKey, toKey } = dateKeys(def);
    const active = valueFrom !== '' || valueTo !== '';

    const activePreset = PRESETS.find((preset) => {
        if (preset.value === 'all') {
            return false;
        }

        const range = presetRange(preset.value, def.timeZone);

        return range.from === valueFrom && range.to === valueTo;
    });

    const handleFrom = (next: string): void => {
        // A start after the current end would fail the server rule; clearing
        // the end is the least surprising repair and keeps both in sync.
        const repairTo = next !== '' && valueTo !== '' && next > valueTo;
        onChange({ [fromKey]: next || null, [toKey]: repairTo ? null : valueTo || null });
    };

    const handleTo = (next: string): void => {
        const repairFrom = next !== '' && valueFrom !== '' && next < valueFrom;
        onChange({ [fromKey]: repairFrom ? null : valueFrom || null, [toKey]: next || null });
    };

    const handlePreset = (preset: string | null): void => {
        if (preset === null) {
            return;
        }

        const range = presetRange(preset as DatePreset, def.timeZone);
        onChange({ [fromKey]: range.from, [toKey]: range.to });
    };

    return (
        <div role="group" aria-labelledby={groupId} className="flex flex-col gap-1.5">
            <span id={groupId} className={labelClass}>
                {def.label}
            </span>
            <div className="grid grid-cols-2 gap-2">
                <label className="flex flex-col gap-1 text-xs text-surface-500 dark:text-surface-300">
                    From
                    <Input
                        id={`filter-from-${uid}`}
                        type="date"
                        value={valueFrom}
                        onChange={(event) => handleFrom(event.target.value)}
                        disabled={disabled}
                        aria-label={`${def.label} from`}
                        className="h-9 w-full py-1.5 text-sm tabular-nums dark:bg-surface-900"
                    />
                </label>
                <label className="flex flex-col gap-1 text-xs text-surface-500 dark:text-surface-300">
                    To
                    <Input
                        id={`filter-to-${uid}`}
                        type="date"
                        value={valueTo}
                        onChange={(event) => handleTo(event.target.value)}
                        disabled={disabled}
                        aria-label={`${def.label} to`}
                        className="h-9 w-full py-1.5 text-sm tabular-nums dark:bg-surface-900"
                    />
                </label>
            </div>

            {def.presets === false ? null : (
                <Select
                    items={PRESETS}
                    value={activePreset?.value ?? null}
                    onValueChange={handlePreset}
                    disabled={disabled}
                >
                    <SelectTrigger className="h-9 w-full" aria-label={`${def.label} presets`}>
                        <Calendar
                            className="size-3.5 text-surface-500 dark:text-surface-300"
                            aria-hidden="true"
                        />
                        <SelectValue placeholder="Presets">
                            {activePreset?.label ?? 'Presets'}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {PRESETS.map((preset) => (
                            <SelectItem key={preset.value} value={preset.value}>
                                {preset.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            {active ? (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-7 self-start px-2 text-xs text-surface-600 dark:text-surface-300"
                    onClick={() => onChange({ [fromKey]: null, [toKey]: null })}
                    disabled={disabled}
                    aria-label={`Clear ${def.label}`}
                >
                    <X className="size-3.5" aria-hidden="true" />
                    Clear dates
                </Button>
            ) : null}
        </div>
    );
}

export default function CrudFilters({
    defs,
    state,
    onChange,
    disabled = false,
    className,
}: CrudFiltersProps) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<TableFilters>(state.filters);

    const activeCount = countActiveDefs(defs, state.filters);
    const draftCount = countActiveDefs(defs, draft);
    // A chip lives outside the panel. Committing one closes the panel so its
    // draft cannot go stale while the table reloads.
    const commitOutsidePanel = (patch: TableFilters): void => {
        setOpen(false);
        onChange(patch);
    };
    const chips = buildChips(defs, state.filters, commitOutsidePanel);
    const draftPatch = buildPatch(defs, draft);
    const dirty = JSON.stringify(draftPatch) !== JSON.stringify(buildPatch(defs, state.filters));

    if (defs.length === 0) {
        return null;
    }

    const setDraftValue = (key: string, value: TableFilterValue): void => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const handleOpenChange = (next: boolean): void => {
        if (next) {
            // A saved view or a back/forward navigation may have replaced the
            // URL since the last open; seed from the committed state.
            setDraft({ ...state.filters });
        }

        setOpen(next);
    };

    const handleApply = (): void => {
        setOpen(false);
        onChange(draftPatch);
    };

    const handleReset = (): void => {
        setDraft({});
        onChange(blankPatch(defs));
    };

    const handleCancel = (): void => {
        setDraft({ ...state.filters });
        setOpen(false);
    };

    return (
        <div className={cn('flex w-full flex-wrap items-center gap-2', className)}>
            <Popover open={open} onOpenChange={handleOpenChange}>
                <PopoverTrigger
                    render={
                        <Button
                            type="button"
                            variant="outline"
                            size="toolbar"
                            disabled={disabled}
                            aria-label={
                                activeCount > 0 ? `Filters, ${activeCount} active` : 'Filters'
                            }
                            className={cn(
                                activeCount > 0 &&
                                    'border-aq-accent/50 bg-aq-accent/10 text-surface-900 dark:bg-aq-accent/15 dark:text-surface-0',
                            )}
                        />
                    }
                >
                    <Filter
                        className={cn(
                            'size-3.5',
                            activeCount > 0
                                ? 'text-aq-accent'
                                : 'text-surface-500 dark:text-surface-300',
                        )}
                        aria-hidden="true"
                    />
                    Filters
                    {activeCount > 0 ? (
                        <span className="rounded-full bg-aq-accent/15 px-1.5 py-0.5 text-[11px] font-semibold text-aq-accent tabular-nums">
                            {activeCount}
                        </span>
                    ) : null}
                </PopoverTrigger>

                <PopoverContent align="start" className="w-[min(92vw,22rem)] gap-0 p-0">
                    <div className="flex items-center justify-between border-b border-aq-shell-line px-4 py-3">
                        <PopoverTitle className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Filters
                        </PopoverTitle>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            onClick={handleReset}
                            disabled={disabled || (draftCount === 0 && activeCount === 0)}
                        >
                            Reset
                        </Button>
                    </div>

                    <div className="flex max-h-[min(60vh,26rem)] flex-col gap-4 overflow-y-auto px-4 py-4">
                        {defs.map((def) => {
                            if (def.type === 'text') {
                                return (
                                    <TextFilter
                                        key={def.key}
                                        def={def}
                                        value={asString(draft[def.key])}
                                        onChange={(value) => setDraftValue(def.key, value)}
                                        disabled={disabled}
                                    />
                                );
                            }

                            if (def.type === 'select') {
                                const searchable = def.searchable ?? def.options.length > 40;

                                return searchable ? (
                                    <SearchableSelectFilter
                                        key={def.key}
                                        def={def}
                                        value={asString(draft[def.key])}
                                        onChange={(value) => setDraftValue(def.key, value)}
                                        disabled={disabled}
                                    />
                                ) : (
                                    <SelectFilter
                                        key={def.key}
                                        def={def}
                                        value={asString(draft[def.key])}
                                        onChange={(value) => setDraftValue(def.key, value)}
                                        disabled={disabled}
                                    />
                                );
                            }

                            if (def.type === 'multi-select') {
                                return (
                                    <MultiSelectFilter
                                        key={def.key}
                                        def={def}
                                        value={asList(draft[def.key])}
                                        onChange={(value) => setDraftValue(def.key, value)}
                                        disabled={disabled}
                                    />
                                );
                            }

                            const { fromKey, toKey } = dateKeys(def);

                            return (
                                <DateRangeFilter
                                    key={def.key}
                                    def={def}
                                    valueFrom={asString(draft[fromKey])}
                                    valueTo={asString(draft[toKey])}
                                    onChange={(patch) =>
                                        setDraft((current) => ({ ...current, ...patch }))
                                    }
                                    disabled={disabled}
                                />
                            );
                        })}
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-aq-shell-line px-4 py-3">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-9 px-3 text-xs"
                            onClick={handleCancel}
                            disabled={disabled}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="h-9 px-4 text-xs"
                            onClick={handleApply}
                            disabled={disabled || !dirty}
                        >
                            Apply
                        </Button>
                    </div>
                </PopoverContent>
            </Popover>

            {chips.map((chip) => (
                <span
                    key={chip.id}
                    className="inline-flex h-7 max-w-64 items-center gap-1 rounded-full border border-aq-shell-line bg-surface-50 pr-1 pl-2.5 text-xs text-surface-700 dark:bg-surface-800 dark:text-surface-200"
                >
                    <span className="truncate">{chip.label}</span>
                    <button
                        type="button"
                        aria-label={`Remove filter ${chip.label}`}
                        onClick={chip.onRemove}
                        disabled={disabled}
                        className="grid size-5 shrink-0 place-items-center rounded-full text-surface-500 transition-colors duration-150 hover:bg-surface-200 hover:text-surface-800 motion-reduce:transition-none dark:hover:bg-surface-700 dark:hover:text-surface-100"
                    >
                        <X className="size-3" aria-hidden="true" />
                    </button>
                </span>
            ))}

            {chips.length > 0 ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs text-surface-600 dark:text-surface-300"
                    onClick={() => commitOutsidePanel(blankPatch(defs))}
                    disabled={disabled}
                >
                    Clear all
                </Button>
            ) : null}
        </div>
    );
}
