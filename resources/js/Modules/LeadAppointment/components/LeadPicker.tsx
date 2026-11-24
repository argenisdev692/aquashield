/**
 * LeadPicker — searchable lead combobox for the appointment screens.
 *
 * The server owns the search (TanStack Query lives in the module hook); this
 * component only owns the open state and the keyboard contract. A selection is
 * reported by uuid so the form never carries a copy of the lead record.
 */
import { useEffect, useId, useRef, useState } from 'react';
import { Loader2, Search, X } from 'lucide-react';
import FieldShell from '@/Components/Forms/FieldShell';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';
import { useLeadOptions } from '../hooks/useLeadOptions';
import type { LeadOption } from '../hooks/useLeadOptions';

interface LeadPickerProps {
    label?: string;
    name?: string;
    value: string;
    onChange: (uuid: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
}

const DEBOUNCE_MS = 250;

export default function LeadPicker({
    label = 'Lead',
    name = 'lead_uuid',
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder = 'Search by name, phone or email',
}: LeadPickerProps) {
    const uid = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const blurTimer = useRef<number | null>(null);

    const [selected, setSelected] = useState<LeadOption | null>(null);
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);

    const visibleSelected = value === '' ? null : selected;
    const leads = useLeadOptions(debounced, open);
    const listboxId = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}-listbox`;

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(search.trim()), DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [search]);

    useEffect(
        () => () => {
            if (blurTimer.current !== null) {
                window.clearTimeout(blurTimer.current);
            }
        },
        [],
    );

    const select = (lead: LeadOption): void => {
        setSelected(lead);
        onChange(lead.uuid);
        setSearch('');
        setDebounced('');
        setActiveIndex(-1);
        setOpen(false);
        inputRef.current?.focus();
    };

    const clear = (): void => {
        setSelected(null);
        onChange('');
        setSearch('');
        setDebounced('');
        setOpen(false);
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Escape') {
            setOpen(false);
            setActiveIndex(-1);

            return;
        }

        if (!open || leads.leads.length === 0) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setOpen(true);
            }

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((index) => (index + 1) % leads.leads.length);

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => (index - 1 + leads.leads.length) % leads.leads.length);

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            const lead = leads.leads[activeIndex];

            if (lead !== undefined) {
                event.preventDefault();
                select(lead);
            }
        }
    };

    const hasPanel = open && !disabled;

    return (
        <FieldShell
            id={`${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`}
            label={label}
            name={name}
            error={error}
            hint={hint}
            required={required}
        >
            {({ id, describedBy, invalid }) => (
                <div className="relative">
                    <Input
                        ref={inputRef}
                        id={id}
                        name={name}
                        type="search"
                        value={open ? search : (visibleSelected?.full_name ?? '')}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setActiveIndex(-1);
                            setOpen(true);

                            if (event.target.value === '' && visibleSelected !== null) {
                                clear();
                            }
                        }}
                        onFocus={() => {
                            if (blurTimer.current !== null) {
                                window.clearTimeout(blurTimer.current);
                            }

                            setOpen(true);
                            setSearch('');
                            setDebounced('');
                        }}
                        onBlur={() => {
                            if (blurTimer.current !== null) {
                                window.clearTimeout(blurTimer.current);
                            }

                            blurTimer.current = window.setTimeout(() => {
                                setOpen(false);
                                setSearch('');
                                setDebounced('');
                                onBlur?.();
                            }, 120);
                        }}
                        onKeyDown={onKeyDown}
                        placeholder={placeholder}
                        disabled={disabled}
                        autoComplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded={hasPanel}
                        aria-controls={hasPanel ? listboxId : undefined}
                        aria-activedescendant={
                            hasPanel && activeIndex >= 0
                                ? `${listboxId}-option-${activeIndex}`
                                : undefined
                        }
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        aria-busy={leads.isFetching || undefined}
                        className={cn('pe-16', error && 'border-aq-danger')}
                    />

                    <div className="pointer-events-none absolute inset-y-0 end-2 flex items-center gap-1">
                        {leads.isFetching && (
                            <Loader2
                                className="size-4 animate-spin text-surface-500 motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {!leads.isFetching && (
                            <Search
                                className="size-4 text-surface-500 dark:text-surface-400"
                                aria-hidden="true"
                            />
                        )}
                    </div>

                    {visibleSelected !== null && !disabled && (
                        <button
                            type="button"
                            aria-label="Clear selected lead"
                            onClick={clear}
                            className="absolute inset-y-1.5 end-8 flex size-7 items-center justify-center rounded-sm text-surface-500 transition-colors duration-150 hover:text-surface-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:hover:text-surface-0"
                        >
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    )}

                    {hasPanel && (
                        <div
                            id={listboxId}
                            role="listbox"
                            aria-label="Lead suggestions"
                            className="absolute inset-x-0 top-full z-30 mt-1.5 overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 shadow-lg dark:bg-surface-900"
                        >
                            {leads.isPending ? (
                                <p
                                    role="status"
                                    className="flex items-center gap-2 px-3 py-2.5 text-sm text-surface-600 dark:text-surface-300"
                                >
                                    <Loader2
                                        className="size-4 animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                    Loading leads…
                                </p>
                            ) : leads.isError ? (
                                <p
                                    role="alert"
                                    className="px-3 py-2.5 text-sm text-aq-danger-strong"
                                >
                                    Leads could not be loaded. Keep typing or try again.
                                </p>
                            ) : leads.leads.length === 0 ? (
                                <p className="px-3 py-2.5 text-sm text-surface-600 dark:text-surface-300">
                                    {debounced === ''
                                        ? 'No leads are available. Create the lead first.'
                                        : `No leads match “${debounced}”. Check the spelling or create the lead first.`}
                                </p>
                            ) : (
                                <ul className="max-h-64 overflow-y-auto py-1">
                                    {leads.leads.map((lead, index) => (
                                        <li key={lead.uuid}>
                                            <button
                                                type="button"
                                                id={`${listboxId}-option-${index}`}
                                                role="option"
                                                aria-selected={index === activeIndex}
                                                onMouseDown={(event) => event.preventDefault()}
                                                onMouseEnter={() => setActiveIndex(index)}
                                                onClick={() => select(lead)}
                                                className={cn(
                                                    'flex w-full flex-col gap-0.5 px-3 py-2 text-left transition-colors duration-150 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none',
                                                    index === activeIndex
                                                        ? 'bg-surface-100 dark:bg-surface-800'
                                                        : 'hover:bg-surface-100 dark:hover:bg-surface-800',
                                                )}
                                            >
                                                <span className="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                                                    {lead.full_name}
                                                </span>
                                                <span className="truncate text-xs text-surface-500 dark:text-surface-400">
                                                    {[lead.phone, lead.city, lead.state]
                                                        .filter(Boolean)
                                                        .join(' · ') || 'No contact details'}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>
            )}
        </FieldShell>
    );
}
