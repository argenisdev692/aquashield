/**
 * ImagesDropzone — multi-image input with previews, reorder and removal.
 *
 * Reorder is native drag plus ArrowLeft / ArrowRight buttons, so touch and
 * keyboard users get the same operation. The preview URLs come from
 * `useFilePreviews`: one per file identity, revoked on remove/unmount, reused
 * across reorders.
 */
import { useId, useState } from 'react';
import { ArrowLeft, ArrowRight, GripVertical, Image as ImageIcon, X } from 'lucide-react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { isImageFile, useFilePreviews } from './useFilePreviews';

interface ImagesDropzoneProps {
    label: string;
    name: string;
    files: File[];
    onFilesChange: (files: File[]) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    maxFiles?: number;
}

export default function ImagesDropzone({
    label,
    name,
    files,
    onFilesChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    maxFiles,
}: ImagesDropzoneProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const previews = useFilePreviews(files);
    const [draggedFile, setDraggedFile] = useState<File | null>(null);
    const [announcement, setAnnouncement] = useState('');

    const move = (file: File, direction: -1 | 1): void => {
        const previewIndex = previews.findIndex((preview) => preview.file === file);
        const target = previews[previewIndex + direction];

        if (previewIndex === -1 || !target) {
            return;
        }

        const from = files.indexOf(file);
        const to = files.indexOf(target.file);

        if (from === -1 || to === -1) {
            return;
        }

        const next = [...files];
        const [moved] = next.splice(from, 1);

        if (!moved) {
            return;
        }

        next.splice(to, 0, moved);
        onFilesChange(next);
        setAnnouncement(
            `Moved image ${previewIndex + 1} to position ${previewIndex + 1 + direction}.`,
        );
    };

    const reorder = (target: File): void => {
        if (!draggedFile) {
            return;
        }

        const from = files.indexOf(draggedFile);
        const to = files.indexOf(target);

        if (from === -1 || to === -1 || from === to) {
            return;
        }

        const next = [...files];
        const [moved] = next.splice(from, 1);

        if (!moved) {
            return;
        }

        next.splice(to, 0, moved);
        onFilesChange(next);
        setAnnouncement(`Moved ${draggedFile.name} to position ${to + 1}.`);
    };

    const full = maxFiles !== undefined && files.length >= maxFiles;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            <div className="flex flex-col gap-3">
                <div className="rounded-lg border-2 border-dashed border-surface-300 p-3 text-center transition-colors hover:border-aq-accent dark:border-surface-700">
                    <Input
                        id={id}
                        name={name}
                        type="file"
                        accept="image/*"
                        multiple
                        onChange={(event) => {
                            const selected = Array.from(event.target.files ?? []).filter(
                                isImageFile,
                            );
                            const signature = (file: File): string =>
                                `${file.name}|${file.size}|${file.lastModified}`;
                            const existing = new Set(files.map(signature));
                            const additions = selected.filter((file) => {
                                const key = signature(file);

                                if (existing.has(key)) {
                                    return false;
                                }

                                existing.add(key);

                                return true;
                            });
                            const remaining =
                                maxFiles !== undefined
                                    ? Math.max(0, maxFiles - files.length)
                                    : additions.length;

                            onFilesChange([...files, ...additions.slice(0, remaining)]);
                            event.target.value = '';
                        }}
                        onBlur={onBlur}
                        disabled={disabled || full}
                        className="hidden"
                    />
                    <label htmlFor={id} className="flex cursor-pointer flex-col items-center gap-2">
                        <ImageIcon className="h-6 w-6" aria-hidden="true" />
                        <p className="text-xs font-medium text-aq-ink-soft">
                            {full ? 'Maximum images reached' : 'Add images'}
                        </p>
                    </label>
                </div>

                {previews.length > 0 && (
                    <div
                        className="flex flex-wrap gap-2"
                        role="list"
                        aria-label={`${label} images`}
                    >
                        {previews.map((preview, index) => {
                            return (
                                <div
                                    key={preview.url}
                                    role="listitem"
                                    draggable={!disabled}
                                    onDragStart={(event) => {
                                        setDraggedFile(preview.file);
                                        event.dataTransfer.effectAllowed = 'move';
                                        event.dataTransfer.setData('text/plain', preview.file.name);
                                    }}
                                    onDragOver={(event) => {
                                        if (!disabled && draggedFile) {
                                            event.preventDefault();
                                            event.dataTransfer.dropEffect = 'move';
                                        }
                                    }}
                                    onDrop={(event) => {
                                        event.preventDefault();
                                        reorder(preview.file);
                                        setDraggedFile(null);
                                    }}
                                    onDragEnd={() => setDraggedFile(null)}
                                    className="relative size-24 cursor-grab overflow-hidden rounded-lg border border-aq-shell-line bg-surface-100 active:cursor-grabbing dark:bg-surface-800"
                                >
                                    <img
                                        src={preview.url}
                                        alt={`Preview ${index + 1}`}
                                        className="size-full object-cover"
                                    />

                                    <GripVertical
                                        className="pointer-events-none absolute top-1 left-1 size-4 text-aq-ink drop-shadow-sm"
                                        aria-hidden="true"
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

                                    <div className="absolute inset-x-0 bottom-0 flex items-center justify-between bg-aq-void/55 px-1 py-0.5">
                                        <button
                                            type="button"
                                            disabled={disabled || index === 0}
                                            aria-label={`Move ${preview.file.name} left`}
                                            className="flex size-6 items-center justify-center rounded-sm text-aq-ink transition-colors duration-150 hover:bg-aq-void/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:pointer-events-none disabled:opacity-30"
                                            onClick={() => move(preview.file, -1)}
                                        >
                                            <ArrowLeft className="size-3.5" aria-hidden="true" />
                                        </button>

                                        <button
                                            type="button"
                                            disabled={disabled || index === previews.length - 1}
                                            aria-label={`Move ${preview.file.name} right`}
                                            className="flex size-6 items-center justify-center rounded-sm text-aq-ink transition-colors duration-150 hover:bg-aq-void/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:pointer-events-none disabled:opacity-30"
                                            onClick={() => move(preview.file, 1)}
                                        >
                                            <ArrowRight className="size-3.5" aria-hidden="true" />
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <p className="sr-only" aria-live="polite">
                    {announcement}
                </p>
            </div>
        </FieldShell>
    );
}
