// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import FileDropzone from './FileDropzone';

const image = (name = 'photo.jpg', bytes = 3): File =>
    new File(['x'.repeat(bytes)], name, { type: 'image/jpeg' });

const pdf = (name = 'invoice.pdf'): File =>
    new File(['%PDF-1.4'], name, { type: 'application/pdf' });

const select = (input: HTMLInputElement, files: File[]): void => {
    Object.defineProperty(input, 'files', {
        configurable: true,
        value: files,
    });
    fireEvent.change(input);
};

const setup = (files: File[] = [], onReject?: (message: string) => void) => {
    const onFilesChange = vi.fn<(next: File[]) => void>();

    const { container } = render(
        <FileDropzone
            label="Attachments"
            name="attachments"
            files={files}
            onFilesChange={onFilesChange}
            onReject={onReject}
            accept="image/jpeg,image/png,application/pdf"
            maxSizeMb={0.002}
            maxFiles={2}
            multiple
        />,
    );

    return {
        input: container.querySelector('input[type="file"]') as HTMLInputElement,
        onFilesChange,
    };
};

afterEach(cleanup);

describe('FileDropzone validation', () => {
    it('accepts an image and a PDF within the type and size limits', () => {
        const { input, onFilesChange } = setup();

        select(input, [image(), pdf()]);

        expect(onFilesChange).toHaveBeenCalledTimes(1);
        expect(onFilesChange.mock.calls[0]?.[0]).toHaveLength(2);
    });

    it('rejects a file larger than the per-file limit without changing the selection', () => {
        const onReject = vi.fn<(message: string) => void>();
        const { input, onFilesChange } = setup([], onReject);

        select(input, [new File([new Uint8Array(4096)], 'big.jpg', { type: 'image/jpeg' })]);

        expect(onFilesChange).not.toHaveBeenCalled();
        expect(onReject).toHaveBeenCalledWith(expect.stringMatching(/larger than/i));
    });

    it('rejects an unsupported type without changing the selection', () => {
        const onReject = vi.fn<(message: string) => void>();
        const { input, onFilesChange } = setup([], onReject);

        select(input, [new File(['notes'], 'notes.txt', { type: 'text/plain' })]);

        expect(onFilesChange).not.toHaveBeenCalled();
        expect(onReject).toHaveBeenCalledWith(expect.stringMatching(/not an accepted file type/i));
    });

    it('appends new files to an existing multiple selection', () => {
        const first = image('first.jpg');
        const second = image('second.jpg');
        const { input, onFilesChange } = setup([first]);

        select(input, [second]);

        expect(onFilesChange).toHaveBeenCalledWith([first, second]);
    });

    it('caps the whole selection at maxFiles', () => {
        const first = image('first.jpg');
        const second = image('second.jpg');
        const third = image('third.jpg');
        const onReject = vi.fn<(message: string) => void>();
        const { input, onFilesChange } = setup([first, second], onReject);

        select(input, [third]);

        expect(onFilesChange).not.toHaveBeenCalled();
        expect(onReject).toHaveBeenCalledWith(expect.stringMatching(/at most 2 files/i));
    });

    it('applies a separate size limit per file category', () => {
        const onFilesChange = vi.fn<(next: File[]) => void>();

        const { container } = render(
            <FileDropzone
                label="Attachments"
                name="attachments"
                files={[]}
                onFilesChange={onFilesChange}
                accept="image/jpeg,application/pdf"
                maxSizeMbByType={{ image: 0.001, pdf: 0.003 }}
                multiple
            />,
        );

        const input = container.querySelector('input[type="file"]') as HTMLInputElement;

        // 2 KB image: over the 1 KB image ceiling.
        select(input, [new File([new Uint8Array(2048)], 'big.jpg', { type: 'image/jpeg' })]);
        expect(onFilesChange).not.toHaveBeenCalled();

        // 2 KB PDF: under the 3 KB PDF ceiling.
        select(input, [new File([new Uint8Array(2048)], 'doc.pdf', { type: 'application/pdf' })]);
        expect(onFilesChange).toHaveBeenCalledTimes(1);
    });

    it('shows the rejection inline when the parent does not handle it', () => {
        const { input } = setup();

        select(input, [new File(['notes'], 'notes.txt', { type: 'text/plain' })]);

        expect(screen.getByRole('alert').textContent).toMatch(/not an accepted file type/i);
    });
});
