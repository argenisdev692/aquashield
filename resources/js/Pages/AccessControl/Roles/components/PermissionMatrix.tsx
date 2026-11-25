/**
 * PermissionMatrix — grouped, searchable checklist for a role.
 *
 * The catalogue is loaded once with the page (514 rows today). Filtering is
 * local; the form submits names, never ids. Group headers carry a per-group
 * All/None control because selecting `USER` (nine capabilities) one box at a
 * time is the common task.
 */
import { useId, useState } from 'react';
import { Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';

type PermissionOption = Modules.AccessControl.Data.PermissionOptionData;

interface PermissionMatrixProps {
    permissions: PermissionOption[];
    value: string[];
    onChange: (next: string[]) => void;
    disabled?: boolean;
    error?: string;
}

const groupOptions = (
    permissions: PermissionOption[],
): { name: string; items: PermissionOption[] }[] => {
    const groups = new Map<string, PermissionOption[]>();

    for (const permission of permissions) {
        const current = groups.get(permission.group);

        if (current === undefined) {
            groups.set(permission.group, [permission]);
        } else {
            current.push(permission);
        }
    }

    return [...groups.entries()]
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([name, items]) => ({ name, items }));
};

/*
 * Ids for the 514 rows cannot come from `useId()` — hooks are not callable in a
 * loop, and an array index would relabel the list the moment the query filters
 * it. Permission names are uppercase identifiers enforced by the server, so the
 * name is already unique and stable; the sanitiser only guarantees the value is
 * a valid id. The field-set error is shared, so it keeps a `useId()`.
 */
const controlId = (name: string): string => `permission-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;

const groupHeadingId = (name: string): string =>
    `permission-group-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;

export default function PermissionMatrix({
    permissions,
    value,
    onChange,
    disabled = false,
    error,
}: PermissionMatrixProps) {
    const [query, setQuery] = useState('');
    const errorId = useId();
    const term = query.trim().toUpperCase();
    const selected = new Set(value);

    const visible =
        term === ''
            ? permissions
            : permissions.filter((permission) => permission.name.includes(term));
    const groups = groupOptions(visible);

    const toggle = (name: string, checked: boolean): void => {
        const next = new Set(value);

        if (checked) {
            next.add(name);
        } else {
            next.delete(name);
        }

        onChange([...next].sort());
    };

    const setGroup = (items: PermissionOption[], checked: boolean): void => {
        const next = new Set(value);

        for (const item of items) {
            if (checked) {
                next.add(item.name);
            } else {
                next.delete(item.name);
            }
        }

        onChange([...next].sort());
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-sm">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-surface-400"
                        aria-hidden="true"
                    />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Filter permissions…"
                        aria-label="Filter permissions"
                        className="pl-9"
                        disabled={disabled}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-xs text-surface-600 tabular-nums dark:text-surface-300">
                        {value.length} of {permissions.length} selected
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            const next = new Set(value);

                            for (const permission of visible) {
                                next.add(permission.name);
                            }

                            onChange([...next].sort());
                        }}
                        disabled={
                            disabled || visible.every((permission) => selected.has(permission.name))
                        }
                    >
                        {term === '' ? 'Select all' : `Select all shown (${visible.length})`}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => onChange([])}
                        disabled={disabled || value.length === 0}
                    >
                        Clear all
                    </Button>
                </div>
            </div>

            <div className="max-h-[34rem] overflow-y-auto rounded-lg border border-aq-shell-line">
                {groups.length === 0 ? (
                    <p className="px-4 py-10 text-center text-sm text-surface-600 dark:text-surface-300">
                        No permission matches “{query}”.
                    </p>
                ) : (
                    groups.map((group) => {
                        const selectedInGroup = group.items.filter((item) =>
                            selected.has(item.name),
                        ).length;
                        const allInGroup = selectedInGroup === group.items.length;

                        return (
                            <section
                                key={group.name}
                                role="group"
                                aria-labelledby={groupHeadingId(group.name)}
                                aria-describedby={error ? errorId : undefined}
                                className="border-b border-aq-shell-line last:border-b-0"
                            >
                                <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-aq-shell-line bg-surface-50/95 px-4 py-2 backdrop-blur dark:bg-surface-800/95">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <h3
                                            id={groupHeadingId(group.name)}
                                            className="truncate text-xs font-semibold tracking-normal text-surface-700 dark:text-surface-200"
                                        >
                                            {group.name}
                                        </h3>
                                        <span className="text-xs text-surface-500 tabular-nums dark:text-surface-300">
                                            {selectedInGroup}/{group.items.length}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => setGroup(group.items, !allInGroup)}
                                        disabled={disabled}
                                    >
                                        {allInGroup ? 'None' : 'All'}
                                    </Button>
                                </header>

                                <div className="grid gap-x-4 gap-y-1 px-4 py-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {group.items.map((permission) => (
                                        <label
                                            key={permission.name}
                                            htmlFor={controlId(permission.name)}
                                            className="flex min-w-0 items-center gap-2 rounded-sm px-1 py-0.5 text-sm text-surface-700 transition-colors duration-150 hover:bg-surface-50 motion-reduce:transition-none dark:text-surface-200 dark:hover:bg-surface-800/60"
                                        >
                                            <Checkbox
                                                id={controlId(permission.name)}
                                                checked={selected.has(permission.name)}
                                                onCheckedChange={(checked) =>
                                                    toggle(permission.name, checked === true)
                                                }
                                                disabled={disabled}
                                            />
                                            <span
                                                className="truncate font-mono text-[0.8rem]"
                                                title={permission.name}
                                            >
                                                {permission.name}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </section>
                        );
                    })
                )}
            </div>

            {error ? (
                <p id={errorId} className="text-sm font-medium text-aq-danger-strong">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
