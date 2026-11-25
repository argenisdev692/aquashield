// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { a11yViolations } from '@/test/a11y';
import type { SimpleColumn } from '@/lib/data-table';
import DataTableKit from './DataTableKit';

/**
 * The search box is local while the query is a page visit. Without the
 * debounce every keystroke called `router.visit`, cancelled the previous one
 * and made the table flicker; with a naive sync, a late server echo could
 * overwrite what the user was still typing. These three behaviours are the
 * whole reason the component owns a draft.
 */
interface Row {
    uuid: string;
    name: string;
}

const columns: SimpleColumn<Row>[] = [{ accessorKey: 'name', header: 'Name', sortable: true }];

const data: Row[] = [
    { uuid: '1', name: 'Ada' },
    { uuid: '2', name: 'Grace' },
];

const createProps = () => ({
    columns,
    data,
    total: data.length,
    page: 1,
    perPage: 15,
    setPage: vi.fn<(page: number) => void>(),
    setPerPage: vi.fn<(perPage: number) => void>(),
    search: '',
    setSearch: vi.fn<(search: string) => void>(),
    sort: null,
    setSort: vi.fn<(sort: { field: string; direction: 'asc' | 'desc' } | null) => void>(),
});

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

const searchInput = (): HTMLElement => screen.getByRole('textbox', { name: /search/i });

describe('DataTableKit search', () => {
    it('debounces the visit instead of firing per keystroke', () => {
        const props = createProps();

        render(<DataTableKit {...props} />);

        fireEvent.change(searchInput(), { target: { value: 'ac' } });

        act(() => {
            vi.advanceTimersByTime(299);
        });

        expect(props.setSearch).not.toHaveBeenCalled();

        act(() => {
            vi.advanceTimersByTime(1);
        });

        expect(props.setSearch).toHaveBeenCalledTimes(1);
        expect(props.setSearch).toHaveBeenCalledWith('ac');
    });

    it('waits for the minimum length and never sends a single character', () => {
        const props = createProps();

        render(<DataTableKit {...props} />);

        fireEvent.change(searchInput(), { target: { value: 'a' } });

        act(() => {
            vi.advanceTimersByTime(5_000);
        });

        expect(props.setSearch).not.toHaveBeenCalled();
    });

    it('does not re-send the value the server just echoed back', () => {
        const props = createProps();

        const { rerender } = render(<DataTableKit {...props} />);

        fireEvent.change(searchInput(), { target: { value: 'acme' } });

        act(() => {
            vi.advanceTimersByTime(300);
        });

        expect(props.setSearch).toHaveBeenCalledTimes(1);

        // The visit lands; the URL now echoes the search back as a prop.
        rerender(<DataTableKit {...props} search="acme" />);

        act(() => {
            vi.advanceTimersByTime(5_000);
        });

        expect(props.setSearch).toHaveBeenCalledTimes(1);
    });

    it('adopts a search that changed outside the input (reset or saved view)', () => {
        const props = createProps();

        const { rerender } = render(<DataTableKit {...props} search="acme" />);

        expect((searchInput() as HTMLInputElement).value).toBe('acme');

        rerender(<DataTableKit {...props} search="" />);

        expect((searchInput() as HTMLInputElement).value).toBe('');
    });
});

describe('DataTableKit accessibility', () => {
    it('has no structural violations with selection, search and the toolbar', async () => {
        // axe schedules its traversal on timers; this file installs fake ones
        // for the debounce suite, which would leave `axe.run` pending forever.
        vi.useRealTimers();

        const { container } = render(
            <DataTableKit
                {...createProps()}
                title="All users"
                description="Staff accounts and their lifecycle state."
                onBulkDelete={vi.fn<(rows: Row[]) => void>()}
                selectionLabel={(row) => row.name}
                rowActions={() => <button type="button">Open</button>}
            />,
        );

        expect(await a11yViolations(container)).toEqual([]);
    }, 15_000);
});

describe('DataTableKit selection', () => {
    it('clears the page selection when the server replaces the rows', () => {
        const props = createProps();
        const onSelectionChange = vi.fn<(ids: string[], rows: Row[]) => void>();

        const { rerender } = render(
            <DataTableKit
                {...props}
                onBulkDelete={vi.fn<(rows: Row[]) => void>()}
                selectionLabel={(row) => row.name}
                onSelectionChange={onSelectionChange}
            />,
        );

        const [, firstRowCheckbox] = screen.getAllByRole('checkbox');

        fireEvent.click(firstRowCheckbox);

        expect(onSelectionChange).toHaveBeenLastCalledWith(['1'], [data[0]]);

        // A new page (or a refetch) hands over a different array identity.
        rerender(
            <DataTableKit
                {...props}
                data={[{ uuid: '9', name: 'Hedy' }]}
                onBulkDelete={vi.fn<(rows: Row[]) => void>()}
                selectionLabel={(row) => row.name}
                onSelectionChange={onSelectionChange}
            />,
        );

        expect(onSelectionChange).toHaveBeenLastCalledWith([], []);
    });
});
