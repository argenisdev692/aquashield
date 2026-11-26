/**
 * FileDropzone — drag & drop file input with label, hint and error.
 *
 * Controlled via `onFilesChange` (FileList -> File[]). Validation runs before
 * the files reach the form: `accept`, `maxSizeMb` (per file) and `maxFiles`
 * (the whole selection). A rejection is shown inline and reported through
 * `onReject`; the current selection is left untouched.
 *
 * The browser is not a trust boundary: the server must repeat the same rules
 * (`mimes`, `max:10240` for a 10 MB per-file ceiling, a `max:` array cap and a
 * PHP `upload_max_filesize` / `post_max_size` that fit the batch).
 *
 * @example Images (10 MB) and PDFs (35 MB), up to 10 files
 * <FileDropzone
 *     label="Attachments"
 *     name="attachments"
 *     files={files}
 *     onFilesChange={setFiles}
 *     accept="image/jpeg,image/png,image/webp,application/pdf"
 *     maxSizeMbByType={{ image: 10, pdf: 35 }}
 *     maxFiles={10}
 *     multiple
 * />
 */
import { useId, useState } from 'react';
import { Upload } from 'lucide-react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { validateFiles, type FileSizeLimits } from '@/lib/files';
import { cn } from '@/lib/utils';

interface FileDropzoneProps {
    label: string;
    name: string;
    files: File[];
    onFilesChange: (files: File[]) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    /** MIME types and/or extensions, e.g. `image/jpeg,.pdf`. */
    accept?: string;
    disabled?: boolean;
    multiple?: boolean;
    /** Per-file ceiling in MB (10 MB = Laravel `max:10240`). */
    maxSizeMb?: number;
    /** Per-category ceiling, e.g. `{ image: 10, pdf: 35 }`; overrides maxSizeMb. */
    maxSizeMbByType?: FileSizeLimits;
    /** Whole-selection ceiling. Omit for no limit. */
    maxFiles?: number;
    /** Receives the first rejection message; the same text is shown inline. */
    onReject?: (message: string) => void;
}

export default function FileDropzone({
    label,
    name,
    files,
    onFilesChange,
    onBlur,
    error,
    hint,
    required = false,
    accept,
    disabled = false,
    multiple = false,
    maxSizeMb,
    maxSizeMbByType,
    maxFiles,
    onReject,
}: FileDropzoneProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const message = error ?? localError;

    /**
     * Validate the selection that would result from this pick. Appending (not
     * replacing) in `multiple` mode is what a gallery/attachment field needs.
     * Files already selected are deduplicated by name + size + mtime.
     */
    const acceptFiles = (list: FileList | null): boolean => {
        const selected = Array.from(list ?? []);

        if (selected.length === 0) {
            return false;
        }

        const existing = multiple ? files : [];
        const signature = (file: File): string => `${file.name}|${file.size}|${file.lastModified}`;
        const seen = new Set(existing.map(signature));
        const additions = selected.filter((file) => {
            const key = signature(file);

            if (seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });

        const combined = multiple ? [...existing, ...additions] : additions.slice(0, 1);
        const rejection = validateFiles(combined, {
            accept,
            maxSizeMb,
            maxSizeMbByType,
            maxFiles,
        });

        if (rejection !== null) {
            setLocalError(rejection);
            onReject?.(rejection);

            return false;
        }

        setLocalError(null);
        onFilesChange(combined);

        return true;
    };

    const limitsHint = (() => {
        const parts: string[] = [];

        if (maxSizeMbByType?.image !== undefined) {
            parts.push(`Images ${maxSizeMbByType.image} MB`);
        }

        if (maxSizeMbByType?.pdf !== undefined) {
            parts.push(`PDFs ${maxSizeMbByType.pdf} MB`);
        }

        if (parts.length === 0 && maxSizeMb !== undefined) {
            parts.push(`Up to ${maxSizeMb} MB per file`);
        }

        if (maxFiles !== undefined) {
            parts.push(`${maxFiles} file${maxFiles === 1 ? '' : 's'} max`);
        }

        return parts.length === 0 ? null : parts.join(' · ');
    })();

    return (
        <FieldShell
            id={id}
            label={label}
            name={name}
            error={message ?? undefined}
            hint={hint}
            required={required}
        >
            {({ describedBy, invalid }) => (
                <div
                    className={cn(
                        'rounded-lg border-2 border-dashed p-4 text-center transition-colors',
                        dragging
                            ? 'border-aq-accent bg-aq-accent/5'
                            : 'border-surface-300 hover:border-aq-accent dark:border-surface-700',
                        'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-aq-focus',
                        disabled && 'opacity-60',
                    )}
                    onDragOver={(event) => {
                        if (disabled) {
                            return;
                        }

                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);

                        if (disabled) {
                            return;
                        }

                        if (acceptFiles(event.dataTransfer?.files ?? null)) {
                            onBlur?.();
                        }
                    }}
                >
                    <Input
                        id={id}
                        name={name}
                        type="file"
                        accept={accept}
                        multiple={multiple}
                        onChange={(event) => {
                            acceptFiles(event.target.files);
                            // Re-selecting the same file must fire change.
                            event.target.value = '';
                        }}
                        onBlur={onBlur}
                        disabled={disabled}
                        aria-describedby={describedBy}
                        aria-invalid={invalid || undefined}
                        className="sr-only"
                    />
                    <label
                        htmlFor={id}
                        className="flex cursor-pointer flex-col items-center gap-2 text-surface-600 dark:text-surface-300"
                    >
                        <Upload className="h-6 w-6" aria-hidden="true" />
                        <p className="text-sm font-medium">Click to upload or drag and drop</p>
                        {limitsHint !== null && (
                            <p className="text-xs text-surface-500 dark:text-surface-300">
                                {limitsHint}
                            </p>
                        )}
                        {files.length > 0 && (
                            <p className="text-xs text-surface-500 dark:text-surface-300">
                                {files.length} file{files.length > 1 ? 's' : ''} selected
                            </p>
                        )}
                    </label>
                </div>
            )}
        </FieldShell>
    );
}
