/**
 * ImageDropzone — image-specific file input with previews.
 *
 * Object URLs are created once per file identity and revoked when the file is
 * removed or the field unmounts; the old version created a new URL on every
 * render and never freed it.
 */
import { useId } from 'react';
import { Image as ImageIcon, X } from 'lucide-react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { useFilePreviews } from './useFilePreviews';

interface ImageDropzoneProps {
    label: string;
    name: string;
    files: File[];
    onFilesChange: (files: File[]) => void;
    /** File-picker hint. The server still re-validates MIME, size and dimensions. */
    accept?: string;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    multiple?: boolean;
}

export default function ImageDropzone({
    label,
    name,
    files,
    onFilesChange,
    accept = 'image/*',
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    multiple = false,
}: ImageDropzoneProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const previews = useFilePreviews(files);

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            {({ describedBy, invalid }) => (
                <div className="flex flex-col gap-3">
                    <div className="rounded-lg border-2 border-dashed border-surface-300 p-3 text-center transition-colors hover:border-aq-accent focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-aq-focus dark:border-surface-700">
                        <Input
                            id={id}
                            name={name}
                            type="file"
                            accept={accept}
                            multiple={multiple}
                            onChange={(event) => {
                                onFilesChange(Array.from(event.target.files ?? []));
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
                            className="flex cursor-pointer flex-col items-center gap-2"
                        >
                            <ImageIcon className="h-6 w-6" aria-hidden="true" />
                            <p className="text-xs font-medium text-aq-ink-soft">Add images</p>
                        </label>
                    </div>

                    {previews.length > 0 && (
                        <div
                            className="flex flex-wrap gap-2"
                            role="list"
                            aria-label={`${label} previews`}
                        >
                            {previews.map((preview, index) => (
                                <div
                                    key={preview.url}
                                    role="listitem"
                                    className="relative size-24 overflow-hidden rounded-lg border border-aq-shell-line"
                                >
                                    <img
                                        src={preview.url}
                                        alt={`Preview ${index + 1}`}
                                        className="size-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        disabled={disabled}
                                        aria-label={`Remove ${preview.file.name}`}
                                        className="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-aq-danger text-aq-void transition-colors duration-150 hover:bg-aq-danger/80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:pointer-events-none disabled:opacity-50"
                                        onClick={() =>
                                            onFilesChange(
                                                files.filter((file) => file !== preview.file),
                                            )
                                        }
                                    >
                                        <X className="h-3 w-3" aria-hidden="true" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </FieldShell>
    );
}
