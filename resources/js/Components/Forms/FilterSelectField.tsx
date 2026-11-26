/**
 * FilterSelectField — searchable single or multi select with an optional
 * remote fetcher.
 *
 * The static mode filters the supplied `options` in the browser. The remote
 * mode owns the query: it debounces keystrokes, aborts the superseded request,
 * keeps a label cache for values that leave the current result set, and skips
 * the client filter so the server remains the only filter. That cache is the
 * difference between an edit form showing "Acme Corp" and showing "42".
 */
import { useEffect, useEffectEvent, useState } from 'react';
import { ChevronsUpDown, Loader2, X } from 'lucide-react';
import FieldShell from './FieldShell';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { OPTION_PREVIEW_LIMIT, previewOptions } from '@/lib/options';
import { cn } from '@/lib/utils';
import type { SelectOption } from './types';

export type FilterSelectValue = string | number;

type BaseProps = {
    label: string;
    name: string;
    /**
     * Static options. With a `fetcher` this list is not searched; it is still
     * read for labels, so seed the already-selected value here on an edit form.
     */
    options?: SelectOption[];
    /**
     * Remote search. When present the server owns filtering; the component
     * disables the local cmdk filter to avoid filtering the result twice.
     */
    fetcher?: (query: string, signal: AbortSignal) => Promise<SelectOption[]>;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    loadingText?: string;
    clearable?: boolean;
    maxVisibleChips?: number;
    minQueryLength?: number;
    debounceMs?: number;
};

type SingleProps = BaseProps & {
    multiple?: false;
    value: FilterSelectValue | null;
    onChange: (value: FilterSelectValue | null) => void;
};

type MultiProps = BaseProps & {
    multiple: true;
    value: FilterSelectValue[];
    onChange: (value: FilterSelectValue[]) => void;
};

export type FilterSelectFieldProps = SingleProps | MultiProps;

const keyOf = (value: FilterSelectValue): string => String(value);

export default function FilterSelectField(props: FilterSelectFieldProps) {
    const {
        label,
        name,
        options,
        fetcher,
        onBlur,
        error,
        hint,
        required = false,
        disabled = false,
        placeholder = 'Select…',
        searchPlaceholder = 'Search…',
        emptyText = 'No results found.',
        loadingText = 'Searching…',
        clearable = true,
        maxVisibleChips = 3,
        minQueryLength = 0,
        debounceMs = 250,
    } = props;

    const multiple = props.multiple === true;
    const hasFetcher = fetcher !== undefined;

    const selectedValues: FilterSelectValue[] = multiple
        ? props.value
        : props.value === null
          ? []
          : [props.value];

    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [remoteOptions, setRemoteOptions] = useState<SelectOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [labelCache, setLabelCache] = useState<Record<string, string>>({});

    const resolvedOptions = hasFetcher ? remoteOptions : (options ?? []);

    const rememberLabels = (list: SelectOption[]): void => {
        setLabelCache((previous) => {
            let changed = false;
            const next = { ...previous };

            for (const option of list) {
                const key = keyOf(option.value);

                if (next[key] !== option.label) {
                    next[key] = option.label;
                    changed = true;
                }
            }

            return changed ? next : previous;
        });
    };

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(search), debounceMs);

        return () => window.clearTimeout(timer);
    }, [search, debounceMs]);

    const runFetcher = useEffectEvent(
        async (query: string, signal: AbortSignal): Promise<SelectOption[]> => {
            if (!fetcher) {
                return [];
            }

            return fetcher(query, signal);
        },
    );

    useEffect(() => {
        if (!hasFetcher || !open) {
            return;
        }

        if (debounced.length < minQueryLength) {
            setRemoteOptions([]);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        let active = true;

        setLoading(true);

        runFetcher(debounced, controller.signal)
            .then((results) => {
                if (!active) {
                    return;
                }

                setRemoteOptions(results);
                rememberLabels(results);
            })
            .catch((reason: unknown) => {
                if (!active) {
                    return;
                }

                if (reason instanceof DOMException && reason.name === 'AbortError') {
                    return;
                }

                setRemoteOptions([]);
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
            controller.abort();
        };
    }, [open, debounced, hasFetcher, minQueryLength]);

    const emit = (next: FilterSelectValue[]): void => {
        if (props.multiple) {
            props.onChange(next);
        } else {
            props.onChange(next[0] ?? null);
        }
    };

    const toggle = (value: FilterSelectValue): void => {
        const key = keyOf(value);
        const exists = selectedValues.some((selected) => keyOf(selected) === key);

        if (!multiple) {
            emit([value]);
            setOpen(false);

            return;
        }

        emit(
            exists
                ? selectedValues.filter((selected) => keyOf(selected) !== key)
                : [...selectedValues, value],
        );
    };

    const labelFor = (value: FilterSelectValue): string => {
        const key = keyOf(value);

        return (
            resolvedOptions.find((option) => keyOf(option.value) === key)?.label ??
            options?.find((option) => keyOf(option.value) === key)?.label ??
            labelCache[key] ??
            key
        );
    };

    const visibleChips = selectedValues.slice(0, maxVisibleChips);
    const hiddenCount = selectedValues.length - visibleChips.length;
    const hasSelection = selectedValues.length > 0;
    const waitingForQuery = hasFetcher && search.length < minQueryLength;
    /*
     * The permission catalogue is ~505 options. Preview an unsearched static
     * list and let typing reach the rest; selected values stay visible.
     */
    const preview = hasFetcher
        ? { options: resolvedOptions, truncated: false }
        : previewOptions(resolvedOptions, selectedValues, search);

    return (
        <FieldShell label={label} name={name} error={error} hint={hint} required={required}>
            {({ id, describedBy, invalid }) => (
                <div className="relative">
                    <Popover
                        open={open}
                        onOpenChange={(next) => {
                            setOpen(next);

                            if (next && hasFetcher) {
                                setSearch('');
                                setDebounced('');
                            }

                            if (!next) {
                                setLoading(false);
                                setSearch('');
                                setDebounced('');
                                onBlur?.();
                            }
                        }}
                    >
                        <PopoverTrigger
                            id={id}
                            /*
                             * Base UI injects `aria-expanded` and `aria-controls`
                             * on its trigger at runtime (PopoverTrigger.js), so
                             * the required pair exists in the DOM. The rule reads
                             * JSX only and cannot see component-injected props.
                             */
                            // oxlint-disable-next-line jsx-a11y/role-has-required-aria-props
                            role="combobox"
                            disabled={disabled}
                            aria-invalid={invalid || undefined}
                            aria-describedby={describedBy}
                            className={cn(
                                'flex min-h-10 w-full items-center gap-2 rounded-md border border-surface-300 bg-surface-0 px-3 py-2 text-left text-sm text-surface-700 transition-colors duration-200',
                                'hover:border-surface-400 focus-visible:border-primary focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-primary',
                                'disabled:cursor-not-allowed disabled:bg-surface-200 disabled:text-surface-500',
                                'dark:border-surface-700 dark:bg-surface-950 dark:text-surface-0 dark:disabled:bg-surface-700',
                                error && 'border-aq-danger',
                                clearable && hasSelection && !disabled ? 'pr-14' : 'pr-9',
                            )}
                        >
                            <span className="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                                {!hasSelection ? (
                                    <span className="truncate text-surface-500 dark:text-surface-300">
                                        {placeholder}
                                    </span>
                                ) : multiple ? (
                                    visibleChips.map((value) => (
                                        <span
                                            key={keyOf(value)}
                                            className="inline-flex max-w-full items-center gap-1 rounded-full bg-aq-elevated px-2 py-0.5 text-xs font-medium text-aq-ink-soft"
                                        >
                                            <span className="truncate">{labelFor(value)}</span>
                                        </span>
                                    ))
                                ) : (
                                    <span className="truncate">{labelFor(selectedValues[0])}</span>
                                )}

                                {hiddenCount > 0 && (
                                    <span className="rounded-full bg-surface-100 px-2 py-0.5 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                        +{hiddenCount}
                                    </span>
                                )}
                            </span>

                            {loading && (
                                <Loader2
                                    className="size-4 shrink-0 animate-spin opacity-60 motion-reduce:animate-none"
                                    aria-hidden="true"
                                />
                            )}

                            <ChevronsUpDown
                                className="size-4 shrink-0 opacity-60"
                                aria-hidden="true"
                            />
                        </PopoverTrigger>

                        <PopoverContent className="w-(--anchor-width) min-w-64 p-0" align="start">
                            <Command shouldFilter={!hasFetcher}>
                                <CommandInput
                                    placeholder={searchPlaceholder}
                                    aria-label={`Search ${label}`}
                                    value={search}
                                    onValueChange={setSearch}
                                />

                                <CommandList>
                                    {loading ? (
                                        <div className="flex items-center gap-2 px-3 py-6 text-sm text-surface-500 dark:text-surface-300">
                                            <Loader2
                                                className="size-4 animate-spin motion-reduce:animate-none"
                                                aria-hidden="true"
                                            />
                                            {loadingText}
                                        </div>
                                    ) : waitingForQuery ? (
                                        <p className="px-3 py-6 text-center text-sm text-surface-500 dark:text-surface-300">
                                            Type at least {minQueryLength} characters to search.
                                        </p>
                                    ) : (
                                        <>
                                            <CommandEmpty>{emptyText}</CommandEmpty>

                                            <CommandGroup>
                                                {preview.options.map((option) => {
                                                    const selected = selectedValues.some(
                                                        (value) =>
                                                            keyOf(value) === keyOf(option.value),
                                                    );

                                                    return (
                                                        <CommandItem
                                                            key={keyOf(option.value)}
                                                            value={`${option.label} ${keyOf(option.value)}`}
                                                            disabled={option.disabled}
                                                            data-checked={selected}
                                                            onSelect={() => toggle(option.value)}
                                                        >
                                                            <span className="truncate">
                                                                {option.label}
                                                            </span>
                                                        </CommandItem>
                                                    );
                                                })}
                                            </CommandGroup>

                                            {preview.truncated ? (
                                                <p className="px-3 py-2 text-center text-xs text-surface-500 dark:text-surface-300">
                                                    Showing {OPTION_PREVIEW_LIMIT} of{' '}
                                                    {resolvedOptions.length}. Type to search them
                                                    all.
                                                </p>
                                            ) : null}
                                        </>
                                    )}
                                </CommandList>
                            </Command>

                            {clearable && hasSelection && !disabled && (
                                <div className="border-t border-surface-200 p-1 dark:border-surface-700">
                                    <button
                                        type="button"
                                        className="w-full rounded-sm px-2 py-1.5 text-left text-xs text-surface-600 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:text-surface-300 dark:hover:bg-surface-800"
                                        onClick={() => emit([])}
                                    >
                                        Clear selection
                                    </button>
                                </div>
                            )}
                        </PopoverContent>
                    </Popover>

                    {clearable && hasSelection && !disabled && (
                        <button
                            type="button"
                            aria-label="Clear selection"
                            className="absolute top-1/2 right-8 flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:text-surface-300 dark:hover:text-surface-0"
                            onClick={() => emit([])}
                        >
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    )}
                </div>
            )}
        </FieldShell>
    );
}
