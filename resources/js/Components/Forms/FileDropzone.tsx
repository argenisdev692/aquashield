/**
 * FileDropzone — drag & drop file input with label, hint and error.
 * Controlled via onFilesChange (FileList -> File[]).
 */
import { useId } from 'react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { Upload } from 'lucide-react';

interface FileDropzoneProps {
    label: string;
    name: string;
    files: File[];
    onFilesChange: (files: File[]) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    accept?: string;
    disabled?: boolean;
    multiple?: boolean;
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
}: FileDropzoneProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            <div className="border-2 border-dashed rounded-lg p-4 text-center transition-colors hover:border-primary-500 dark:hover:border-primary-400">
                <Input
                    id={id}
                    name={name}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    onChange={(e) => onFilesChange(Array.from(e.target.files ?? []))}
                    onBlur={onBlur}
                    disabled={disabled}
                    className="hidden"
                />
                <label
                    htmlFor={id}
                    className="cursor-pointer flex flex-col items-center gap-2 text-surface-600 dark:text-surface-300"
                >
                    <Upload className="h-6 w-6" aria-hidden="true" />
                    <p className="text-sm font-medium">Click to upload or drag and drop</p>
                    {files.length > 0 && (
                        <p className="text-xs text-surface-500 dark:text-surface-400">
                            {files.length} file{files.length > 1 ? 's' : ''} selected
                        </p>
                    )}
                </label>
            </div>
        </FieldShell>
    );
}
