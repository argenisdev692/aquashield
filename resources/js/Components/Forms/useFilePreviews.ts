/**
 * useFilePreviews — object URLs for a controlled `File[]`, with lifecycle.
 *
 * `URL.createObjectURL` has no GC: every call allocates a URL that survives
 * until `revokeObjectURL`. Creating one in render (the old dropzone did) both
 * leaks and churns a new URL on every keystroke elsewhere in the form. This
 * hook creates one URL per file identity, reuses it across reorders, revokes
 * the URLs of removed files, and revokes everything on unmount.
 *
 * The URL cache lives in a ref and is only touched inside effects, so the
 * state updater stays pure (React may call it twice in StrictMode).
 */
import { useEffect, useRef, useState } from 'react';

export interface FilePreview {
    file: File;
    url: string;
}

export const isImageFile = (file: File): boolean => file.type.startsWith('image/');

export function useFilePreviews(
    files: File[],
    filter: (file: File) => boolean = isImageFile,
): FilePreview[] {
    const [previews, setPreviews] = useState<FilePreview[]>([]);
    const urlCache = useRef(new Map<File, string>());

    useEffect(() => {
        const cache = urlCache.current;
        const seen = new Set<File>();
        const next: FilePreview[] = [];

        for (const file of files) {
            if (!filter(file) || seen.has(file)) {
                continue;
            }

            seen.add(file);

            let url = cache.get(file);

            if (!url) {
                url = URL.createObjectURL(file);
                cache.set(file, url);
            }

            next.push({ file, url });
        }

        for (const [file, url] of cache) {
            if (!files.includes(file)) {
                URL.revokeObjectURL(url);
                cache.delete(file);
            }
        }

        setPreviews((previous) => {
            const unchanged =
                previous.length === next.length &&
                previous.every((preview, index) => preview.url === next[index]?.url);

            return unchanged ? previous : next;
        });
    }, [files, filter]);

    useEffect(
        () => () => {
            for (const url of urlCache.current.values()) {
                URL.revokeObjectURL(url);
            }

            urlCache.current.clear();
        },
        [],
    );

    return previews;
}
