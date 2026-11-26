/**
 * DropzoneField — the shared drop surface behind the upload fields.
 *
 * Native `<input type="file">` is the control; the styled area is only a
 * label. That keeps the picker keyboard-reachable and gives mobile the OS
 * sheet. Drag state is visual only — the drop handler is the contract.
 */
import { useId, useState } from 'react';
import { validateFiles } from '@/lib/files';
import { cn } from '@/lib/utils';

interface DropzoneFieldProps {
    label: string;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    maxSizeMb?: number;
    disabled?: boolean;
    hint?: string;
    error?: string;
    required?: boolean;
    actionLabel?: string;
    onFiles: (files: File[]) => void;
    onReject?: (message: string) => void;
    children: (state: { dragging: boolean }) => React.ReactNode;
}

export default function DropzoneField({
    label,
    accept,
    multiple = false,
    maxFiles,
    maxSizeMb = 10,
    disabled = false,
    hint,
    error,
    required = false,
    actionLabel = 'Choose file',
    onFiles,
    onReject,
    children,
}: DropzoneFieldProps) {
    const inputId = useId();
    const hintId = useId();
    const errorId = useId();

    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const message = error ?? localError ?? undefined;

    const describedByIds = [
        hint && !message ? hintId : undefined,
        message ? errorId : undefined,
    ].filter((value): value is string => value !== undefined);
    const describedBy = describedByIds.length > 0 ? describedByIds.join(' ') : undefined;

    const acceptFiles = (files: File[]): void => {
        const selected = multiple ? files : files.slice(0, 1);
        const rejection = validateFiles(selected, {
            accept,
            maxSizeMb,
            maxFiles: multiple ? maxFiles : 1,
        });

        if (rejection !== null) {
            setLocalError(rejection);
            onReject?.(rejection);
            return;
        }

        setLocalError(null);
        onFiles(selected);
    };

    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium text-surface-800 dark:text-surface-100">
                {label}
                {required && (
                    <>
                        <span className="text-aq-danger-strong" aria-hidden="true">
                            *
                        </span>
                        <span className="sr-only">(required)</span>
                    </>
                )}
            </span>

            <div
                className={cn(
                    'rounded-xl border border-dashed p-3 transition-colors duration-150 motion-reduce:transition-none',
                    dragging
                        ? 'border-primary bg-primary-50/60 dark:bg-primary-950/30'
                        : 'border-content-border',
                    message ? 'border-aq-danger/70' : '',
                    disabled ? 'opacity-60' : '',
                )}
                onDragEnter={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={(event) => {
                    event.preventDefault();
                    setDragging(false);
                }}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    acceptFiles(Array.from(event.dataTransfer?.files ?? []));
                }}
            >
                {children({ dragging })}

                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <label
                        htmlFor={inputId}
                        className="inline-flex cursor-pointer items-center gap-2 rounded-md border border-content-border bg-surface-0 px-3 py-1.5 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary-500 dark:bg-surface-900 dark:text-surface-100 dark:hover:bg-surface-800 motion-reduce:transition-none"
                    >
                        {actionLabel}
                    </label>
                    <span className="text-xs text-surface-600 dark:text-surface-300">
                        or drag and drop
                    </span>
                </div>

                <input
                    id={inputId}
                    className="sr-only"
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    disabled={disabled}
                    aria-label={label}
                    aria-describedby={describedBy}
                    aria-invalid={message ? true : undefined}
                    onChange={(event) => {
                        acceptFiles(Array.from(event.target.files ?? []));
                        event.target.value = '';
                    }}
                />
            </div>

            {hint && !message && (
                <p id={hintId} className="text-xs text-surface-600 dark:text-surface-300">
                    {hint}
                </p>
            )}

            {message && (
                <p id={errorId} role="alert" className="text-xs font-medium text-aq-danger-strong">
                    {message}
                </p>
            )}
        </div>
    );
}
