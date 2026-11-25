// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, renderHook } from '@testing-library/react';
import { useFilePreviews } from './useFilePreviews';

/**
 * `URL.createObjectURL` has no GC: a URL that is not revoked pins the whole
 * File in memory for the life of the document. The dropzones reorder and
 * remove files constantly, so both edges are asserted.
 */
const createObjectURL = vi.fn<(file: File) => string>();
const revokeObjectURL = vi.fn<(url: string) => void>();

const image = (name: string): File => new File(['x'], name, { type: 'image/png' });

beforeEach(() => {
    createObjectURL.mockReset();
    revokeObjectURL.mockReset();
    createObjectURL.mockImplementation((file: File) => `blob:${file.name}`);

    URL.createObjectURL = createObjectURL;
    URL.revokeObjectURL = revokeObjectURL;
});

afterEach(() => {
    cleanup();
});

describe('useFilePreviews', () => {
    it('creates one URL per file, not per render', () => {
        const first = image('a.png');
        const second = image('b.png');

        const { rerender } = renderHook(({ files }) => useFilePreviews(files), {
            initialProps: { files: [first, second] },
        });

        rerender({ files: [first, second] });

        expect(createObjectURL).toHaveBeenCalledTimes(2);
    });

    it('reuses the URL across a reorder', () => {
        const first = image('a.png');
        const second = image('b.png');

        const { result, rerender } = renderHook(({ files }) => useFilePreviews(files), {
            initialProps: { files: [first, second] },
        });

        rerender({ files: [second, first] });

        expect(result.current.map((preview) => preview.url)).toEqual(['blob:b.png', 'blob:a.png']);
        expect(createObjectURL).toHaveBeenCalledTimes(2);
        expect(revokeObjectURL).not.toHaveBeenCalled();
    });

    it('revokes the URL of a removed file only', () => {
        const first = image('a.png');
        const second = image('b.png');

        const { rerender } = renderHook(({ files }) => useFilePreviews(files), {
            initialProps: { files: [first, second] },
        });

        rerender({ files: [second] });

        expect(revokeObjectURL).toHaveBeenCalledTimes(1);
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:a.png');
    });

    it('revokes everything on unmount', () => {
        const files = [image('a.png'), image('b.png')];

        const { unmount } = renderHook(({ current }) => useFilePreviews(current), {
            initialProps: { current: files },
        });

        unmount();

        expect(revokeObjectURL).toHaveBeenCalledTimes(2);
    });

    it('skips files the filter rejects without allocating a URL', () => {
        const pdf = new File(['x'], 'contract.pdf', { type: 'application/pdf' });

        const { result } = renderHook(() => useFilePreviews([pdf]));

        expect(result.current).toEqual([]);
        expect(createObjectURL).not.toHaveBeenCalled();
    });
});
