/**
 * File helpers shared by the dropzones.
 *
 * Kept out of the components so the accept/size rules can be tested and reused
 * by a page's Zod schema if it needs to validate the same `File[]` again.
 */

export interface FileSizeLimits {
    image?: number;
    pdf?: number;
}

export interface FileValidationOptions {
    accept?: string;
    /** Fallback ceiling for every file, in MB. */
    maxSizeMb?: number;
    /** Per-category ceiling, in MB; overrides `maxSizeMb` for that category. */
    maxSizeMbByType?: FileSizeLimits;
    maxFiles?: number;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value >= 10 ? value.toFixed(0) : value.toFixed(1)} ${units[unit]}`;
}

function matchesAccept(file: File, accept: string): boolean {
    return accept
        .split(',')
        .map((token) => token.trim().toLowerCase())
        .filter((token) => token !== '')
        .some((token) => {
            if (token.startsWith('.')) {
                return file.name.toLowerCase().endsWith(token);
            }

            if (token.endsWith('/*')) {
                return file.type.toLowerCase().startsWith(token.slice(0, -1));
            }

            return file.type.toLowerCase() === token;
        });
}

const fileCategory = (file: File): keyof FileSizeLimits | null => {
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        return 'pdf';
    }

    return file.type.startsWith('image/') ? 'image' : null;
};

export function validateFiles(files: File[], options: FileValidationOptions = {}): string | null {
    if (files.length === 0) {
        return 'Choose a file first.';
    }

    if (options.maxFiles !== undefined && files.length > options.maxFiles) {
        return `Choose at most ${options.maxFiles} file${options.maxFiles === 1 ? '' : 's'}.`;
    }

    for (const file of files) {
        if (options.accept !== undefined && options.accept !== '') {
            if (!matchesAccept(file, options.accept)) {
                return `${file.name} is not an accepted file type.`;
            }
        }

        const category = fileCategory(file);
        const limit =
            (category !== null ? options.maxSizeMbByType?.[category] : undefined) ??
            options.maxSizeMb;

        if (limit !== undefined) {
            const bytes = limit * 1024 * 1024;

            if (file.size > bytes) {
                return `${file.name} is larger than ${limit} MB.`;
            }
        }
    }

    return null;
}
