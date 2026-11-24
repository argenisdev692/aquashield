/**
 * AddressAutocompleteField — reusable US address input backed by Google
 * Places Autocomplete (New).
 *
 * The field owns only the street line. When the user picks a suggestion it
 * hands the full `AddressSelection` to `onAddressSelect` so the parent can
 * fill city / state / ZIP / country / coordinates. `address_2` is never part
 * of the selection: apartment and suite numbers stay manual.
 *
 * Keyboard contract: ArrowUp/Down move the active option, Enter selects it,
 * Escape closes the listbox. The input exposes the combobox pattern
 * (`role`, `aria-expanded`, `aria-controls`, `aria-activedescendant`).
 */
import { useEffect, useId, useRef, useState } from 'react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';
import { Loader2, MapPin, Search } from 'lucide-react';
import {
    createAutocompleteSessionToken,
    fetchAddressSuggestions,
    PlacesConfigurationError,
    toAddressSelection,
} from '@/lib/google-maps';
import type { AddressSelection, AddressSuggestion } from '@/lib/google-maps';

interface AddressAutocompleteFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onAddressSelect?: (selection: AddressSelection) => void;
    /** Browser key from the server page props. Public by design. */
    apiKey: string;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
}

const MIN_QUERY_LENGTH = 3;
const DEBOUNCE_MS = 280;

const messageFrom = (error: unknown): string => {
    if (error instanceof PlacesConfigurationError) {
        return error.message;
    }

    if (error instanceof Error && error.message !== '') {
        return error.message;
    }

    return 'Address search is unavailable right now. Type the address manually or try again.';
};

export default function AddressAutocompleteField({
    label,
    name,
    value,
    onChange,
    onAddressSelect,
    apiKey,
    onBlur,
    error,
    hint,
    required = false,
    placeholder = 'Start typing a US street address',
    disabled = false,
}: AddressAutocompleteFieldProps) {
    const uid = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const requestSequence = useRef(0);
    const sessionToken = useRef<google.maps.places.AutocompleteSessionToken | null>(null);
    const lastSelected = useRef<string | null>(null);
    const blurTimer = useRef<number | null>(null);

    const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [suggestionError, setSuggestionError] = useState<string | null>(null);

    const listboxId = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}-listbox`;

    useEffect(
        () => () => {
            // A late Google response must not touch an unmounted field.
            requestSequence.current += 1;

            if (blurTimer.current !== null) {
                window.clearTimeout(blurTimer.current);
            }
        },
        [],
    );

    useEffect(() => {
        const query = value.trim();

        if (
            disabled ||
            apiKey.trim() === '' ||
            query.length < MIN_QUERY_LENGTH ||
            value === lastSelected.current
        ) {
            requestSequence.current += 1;
            setSuggestions([]);
            setSuggestionError(null);
            setLoading(false);
            setOpen(false);

            return;
        }

        const sequence = requestSequence.current + 1;
        requestSequence.current = sequence;
        setLoading(true);
        setSuggestionError(null);

        const timer = window.setTimeout(() => {
            void (async () => {
                try {
                    sessionToken.current ??= await createAutocompleteSessionToken(apiKey);
                    const results = await fetchAddressSuggestions(
                        apiKey,
                        query,
                        sessionToken.current,
                    );

                    if (requestSequence.current !== sequence) {
                        return;
                    }

                    setSuggestions(results);
                    setActiveIndex(-1);
                    setOpen(document.activeElement === inputRef.current);
                } catch (caught) {
                    if (requestSequence.current !== sequence) {
                        return;
                    }

                    setSuggestions([]);
                    setSuggestionError(messageFrom(caught));
                    setOpen(document.activeElement === inputRef.current);
                } finally {
                    if (requestSequence.current === sequence) {
                        setLoading(false);
                    }
                }
            })();
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [apiKey, disabled, value]);

    const handleInput = (next: string): void => {
        lastSelected.current = null;
        setActiveIndex(-1);
        onChange(next);
    };

    const selectSuggestion = async (suggestion: AddressSuggestion): Promise<void> => {
        setOpen(false);
        setActiveIndex(-1);
        setLoading(true);
        setSuggestionError(null);

        try {
            const selection = await toAddressSelection(suggestion);
            lastSelected.current = selection.address;
            sessionToken.current = null;
            onChange(selection.address);
            onAddressSelect?.(selection);
        } catch (caught) {
            setSuggestionError(messageFrom(caught));
            setOpen(true);
        } finally {
            setLoading(false);
        }
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Escape') {
            setOpen(false);
            setActiveIndex(-1);

            return;
        }

        if (!open || suggestions.length === 0) {
            if (event.key === 'ArrowDown' && suggestions.length > 0) {
                event.preventDefault();
                setOpen(true);
            }

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((index) => (index + 1) % suggestions.length);

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => (index - 1 + suggestions.length) % suggestions.length);

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            const suggestion = suggestions[activeIndex];

            if (suggestion !== undefined) {
                event.preventDefault();
                void selectSuggestion(suggestion);
            }
        }
    };

    const hasPanel =
        open &&
        (loading ||
            suggestionError !== null ||
            suggestions.length > 0 ||
            value.trim().length >= MIN_QUERY_LENGTH);

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
                        type="text"
                        value={value}
                        onChange={(event) => handleInput(event.target.value)}
                        onKeyDown={onKeyDown}
                        onFocus={() => {
                            if (blurTimer.current !== null) {
                                window.clearTimeout(blurTimer.current);
                            }

                            if (suggestions.length > 0 || suggestionError !== null) {
                                setOpen(true);
                            }
                        }}
                        onBlur={() => {
                            // Let a click on an option land before the panel closes.
                            if (blurTimer.current !== null) {
                                window.clearTimeout(blurTimer.current);
                            }

                            blurTimer.current = window.setTimeout(() => {
                                setOpen(false);
                                onBlur?.();
                            }, 120);
                        }}
                        placeholder={placeholder}
                        disabled={disabled}
                        autoComplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded={hasPanel}
                        aria-controls={hasPanel ? listboxId : undefined}
                        aria-activedescendant={
                            open && activeIndex >= 0
                                ? `${listboxId}-option-${activeIndex}`
                                : undefined
                        }
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        aria-busy={loading || undefined}
                        className={cn('pr-10', error && 'border-aq-danger')}
                    />

                    {loading ? (
                        <Loader2
                            className="pointer-events-none absolute inset-y-0 end-3 my-auto size-4 animate-spin text-surface-500 motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                    ) : (
                        <MapPin
                            className="pointer-events-none absolute inset-y-0 end-3 my-auto size-4 text-surface-500 dark:text-surface-400"
                            aria-hidden="true"
                        />
                    )}

                    {hasPanel && (
                        <div
                            id={listboxId}
                            role="listbox"
                            aria-label={`${label} suggestions`}
                            className="absolute inset-x-0 top-full z-30 mt-1.5 overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 shadow-lg dark:bg-surface-900"
                        >
                            {loading && (
                                <div
                                    role="status"
                                    className="flex items-center gap-2 px-3 py-2.5 text-sm text-surface-600 dark:text-surface-300"
                                >
                                    <Search className="size-3.5" aria-hidden="true" />
                                    Searching US addresses…
                                </div>
                            )}

                            {!loading && suggestionError && (
                                <div
                                    role="alert"
                                    className="px-3 py-2.5 text-sm text-aq-danger-strong"
                                >
                                    {suggestionError}
                                </div>
                            )}

                            {!loading &&
                                !suggestionError &&
                                suggestions.length === 0 &&
                                value.trim().length >= MIN_QUERY_LENGTH && (
                                    <div className="px-3 py-2.5 text-sm text-surface-600 dark:text-surface-300">
                                        No US addresses found. Keep typing or enter the address
                                        manually.
                                    </div>
                                )}

                            {!loading &&
                                !suggestionError &&
                                suggestions.map((suggestion, index) => (
                                    <button
                                        key={suggestion.id}
                                        id={`${listboxId}-option-${index}`}
                                        type="button"
                                        role="option"
                                        aria-selected={index === activeIndex}
                                        onMouseDown={(event) => event.preventDefault()}
                                        onMouseEnter={() => setActiveIndex(index)}
                                        onClick={() => void selectSuggestion(suggestion)}
                                        className={cn(
                                            'flex w-full items-start gap-2.5 px-3 py-2.5 text-left transition-colors duration-150 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none',
                                            index === activeIndex
                                                ? 'bg-surface-100 dark:bg-surface-800'
                                                : 'hover:bg-surface-100 dark:hover:bg-surface-800',
                                        )}
                                    >
                                        <MapPin
                                            className="mt-0.5 size-4 shrink-0 text-aq-accent"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                                                {suggestion.main_text}
                                            </span>
                                            {suggestion.secondary_text !== '' && (
                                                <span className="mt-0.5 block truncate text-xs text-surface-600 dark:text-surface-300">
                                                    {suggestion.secondary_text}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                ))}

                            <div className="border-t border-aq-shell-line px-3 py-1.5 text-end text-[10px] font-medium tracking-wide text-surface-500 dark:text-surface-400">
                                Powered by Google
                            </div>
                        </div>
                    )}
                </div>
            )}
        </FieldShell>
    );
}
