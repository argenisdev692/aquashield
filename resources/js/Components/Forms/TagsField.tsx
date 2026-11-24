/**
 * TagsField — free-form `string[]` input, one entry at a time.
 *
 * Enter or comma commits the draft; Backspace on an empty draft removes the
 * last tag. Pasting a comma- or newline-separated list commits every entry.
 * Duplicates are case-insensitive because CRM tags are looked up, not
 * displayed verbatim.
 */
import { useRef, useState } from 'react';
import { X } from 'lucide-react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

interface TagsFieldProps {
    label: string;
    name: string;
    value: string[];
    onChange: (value: string[]) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    maxTags?: number;
}

export default function TagsField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder = 'Add a tag and press Enter',
    maxTags,
}: TagsFieldProps) {
    const [draft, setDraft] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    const atLimit = maxTags !== undefined && value.length >= maxTags;

    const addTags = (raw: string): void => {
        const candidates = raw
            .split(/[,\n]/)
            .map((entry) => entry.trim())
            .filter((entry) => entry !== '');

        if (candidates.length === 0) {
            return;
        }

        const seen = new Set(value.map((tag) => tag.toLowerCase()));
        const next = [...value];

        for (const candidate of candidates) {
            const key = candidate.toLowerCase();

            if (seen.has(key)) {
                continue;
            }

            if (maxTags !== undefined && next.length >= maxTags) {
                break;
            }

            seen.add(key);
            next.push(candidate);
        }

        if (next.length !== value.length) {
            onChange(next);
        }

        setDraft('');
    };

    const removeTag = (index: number): void => {
        onChange(value.filter((_, current) => current !== index));
        inputRef.current?.focus();
    };

    return (
        <FieldShell label={label} name={name} error={error} hint={hint} required={required}>
            {({ id, describedBy, invalid }) => (
                <div
                    className={cn(
                        'flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-md border border-surface-300 bg-surface-0 px-2 py-1.5 transition-colors focus-within:border-primary',
                        'dark:border-surface-700 dark:bg-surface-950',
                        error && 'border-aq-danger',
                        disabled &&
                            'cursor-not-allowed bg-surface-200 opacity-60 dark:bg-surface-700',
                    )}
                    onClick={(event) => {
                        if (!(event.target as HTMLElement).closest('button')) {
                            inputRef.current?.focus();
                        }
                    }}
                >
                    {value.map((tag, index) => (
                        <span
                            key={`${tag}-${index}`}
                            className="inline-flex max-w-full items-center gap-1 rounded-full bg-aq-elevated px-2 py-0.5 text-xs font-medium text-aq-ink-soft"
                        >
                            <span className="truncate">{tag}</span>
                            <button
                                type="button"
                                disabled={disabled}
                                aria-label={`Remove ${tag}`}
                                className="rounded-full p-0.5 transition-colors duration-150 hover:bg-surface-0/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:pointer-events-none"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    removeTag(index);
                                }}
                            >
                                <X className="size-3" aria-hidden="true" />
                            </button>
                        </span>
                    ))}

                    <Input
                        ref={inputRef}
                        id={id}
                        name={name}
                        type="text"
                        value={draft}
                        disabled={disabled || atLimit}
                        placeholder={atLimit ? 'Maximum tags reached' : placeholder}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        className={cn(
                            'h-7 min-w-24 flex-1 border-0 bg-transparent px-1 py-0 shadow-none',
                            'focus:border-0 dark:bg-transparent',
                            'aria-invalid:border-0',
                        )}
                        onChange={(event) => {
                            const raw = event.target.value;

                            if (raw.includes(',')) {
                                addTags(raw);

                                return;
                            }

                            setDraft(raw);
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ',') {
                                event.preventDefault();
                                addTags(draft);

                                return;
                            }

                            if (event.key === 'Backspace' && draft === '' && value.length > 0) {
                                event.preventDefault();
                                removeTag(value.length - 1);
                            }
                        }}
                        onPaste={(event) => {
                            const text = event.clipboardData.getData('text');

                            if (/[,\n]/.test(text)) {
                                event.preventDefault();
                                addTags(text);
                            }
                        }}
                        onBlur={() => {
                            if (draft.trim() !== '') {
                                addTags(draft);
                            }

                            onBlur?.();
                        }}
                    />
                </div>
            )}
        </FieldShell>
    );
}
