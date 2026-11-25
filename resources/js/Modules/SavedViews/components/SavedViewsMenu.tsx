/**
 * SavedViewsMenu — apply, save and delete a personal table view.
 *
 * The menu never builds a URL: it hands the stored state back to
 * `useCrudTable.apply`, which rewrites the query string. The name is the only
 * user input, bounded by the server (1–60 characters, one view per name).
 */
import { useId, useState } from 'react';
import { Bookmark, Check, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { CrudDialog } from '@/Components/DataTable';
import type { TableFilters, TableState } from '@/lib/table';
import { cn } from '@/lib/utils';
import { useSavedViews } from '../hooks/useSavedViews';
import type { SavedView } from '../hooks/useSavedViews';

interface SavedViewsMenuProps {
    resource: 'users' | 'leads' | 'appointments' | 'backups' | 'activity';
    state: TableState;
    onApply: (state: Partial<Omit<TableState, 'page'>>) => void;
}

const viewToState = (view: SavedView): Partial<Omit<TableState, 'page'>> => ({
    search: view.search ?? '',
    perPage: view.per_page,
    sort:
        view.sort_field === null
            ? null
            : { field: view.sort_field, order: view.sort_order === -1 ? -1 : 1 },
    filters: view.filters,
});

const normalizeFilterMap = (filters: TableFilters): Record<string, string> => {
    const out: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (typeof value === 'string' && value.trim() !== '') {
            out[key] = value.trim();
        } else if (typeof value === 'number') {
            out[key] = String(value);
        } else if (Array.isArray(value) && value.length > 0) {
            out[key] = value.map(String).join(',');
        }
    }

    return out;
};

const isViewActive = (view: SavedView, state: TableState): boolean => {
    if ((view.search ?? '').trim() !== state.search.trim()) {
        return false;
    }

    const viewFilters = normalizeFilterMap(view.filters);
    const stateFilters = normalizeFilterMap(state.filters);
    const viewKeys = Object.keys(viewFilters);
    const stateKeys = Object.keys(stateFilters);

    if (viewKeys.length !== stateKeys.length) {
        return false;
    }

    return viewKeys.every((key) => viewFilters[key] === stateFilters[key]);
};

export default function SavedViewsMenu({ resource, state, onApply }: SavedViewsMenuProps) {
    const uid = useId();
    const [open, setOpen] = useState(false);
    const [saveOpen, setSaveOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<SavedView | null>(null);
    const [name, setName] = useState('');
    const controller = useSavedViews(resource);

    const activeView = controller.views.find((view) => isViewActive(view, state)) ?? null;

    const apply = (view: SavedView): void => {
        onApply(viewToState(view));
        setOpen(false);
    };

    const submit = (): void => {
        const trimmed = name.trim();

        if (trimmed === '') {
            return;
        }

        controller.save.mutate(
            { name: trimmed, state },
            {
                onSuccess: () => {
                    setSaveOpen(false);
                    setName('');
                },
            },
        );
    };

    return (
        <>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <Button
                            type="button"
                            variant="outline"
                            size="toolbar"
                            className={cn(
                                activeView !== null &&
                                    'border-aq-accent/50 bg-aq-accent/10 text-surface-900 dark:border-aq-accent/50 dark:bg-aq-accent/15 dark:text-surface-0',
                            )}
                        />
                    }
                >
                    <Bookmark
                        className={cn('size-3.5', activeView !== null && 'text-aq-accent')}
                        aria-hidden="true"
                    />
                    <span className="max-w-36 truncate">
                        {activeView !== null ? activeView.name : 'Views'}
                    </span>
                    {controller.views.length > 0 ? (
                        <span className="rounded-full bg-surface-100 px-1.5 py-0.5 text-[11px] font-semibold text-surface-600 tabular-nums dark:bg-surface-800 dark:text-surface-300">
                            {controller.views.length}
                        </span>
                    ) : null}
                </PopoverTrigger>

                <PopoverContent align="start" className="w-72 p-2">
                    {controller.isLoading ? (
                        <p className="px-2 py-3 text-sm text-surface-500 dark:text-surface-300">
                            Loading views…
                        </p>
                    ) : controller.isError ? (
                        <div className="flex flex-col gap-2 px-2 py-2">
                            <p role="alert" className="text-sm text-destructive">
                                Saved views could not be loaded.
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={controller.retry}
                            >
                                Try again
                            </Button>
                        </div>
                    ) : controller.views.length === 0 ? (
                        <p className="px-2 py-3 text-sm text-surface-500 dark:text-surface-300">
                            No saved views yet. Set your filters, then save the view.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-0.5">
                            {controller.views.map((view) => {
                                const isActive = activeView?.uuid === view.uuid;

                                return (
                                    <li key={view.uuid} className="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className={cn(
                                                'h-8 min-w-0 flex-1 justify-start gap-2 px-2 font-normal',
                                                isActive &&
                                                    'bg-aq-accent/10 font-medium text-surface-900 dark:bg-aq-accent/15 dark:text-surface-0',
                                            )}
                                            onClick={() => apply(view)}
                                        >
                                            <Check
                                                className={cn(
                                                    'size-3.5 shrink-0 text-aq-accent transition-opacity duration-150',
                                                    isActive ? 'opacity-100' : 'opacity-0',
                                                )}
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">{view.name}</span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Delete view ${view.name}`}
                                            disabled={controller.remove.isPending}
                                            onClick={() => setDeleteTarget(view)}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden="true" />
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {controller.remove.error ? (
                        <p role="alert" className="px-2 pt-1 text-xs text-destructive">
                            {controller.remove.error}
                        </p>
                    ) : null}

                    <div className="mt-1 border-t border-surface-200/80 pt-2 dark:border-surface-700">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-full justify-start"
                            onClick={() => {
                                setOpen(false);
                                setName(activeView?.name ?? '');
                                setSaveOpen(true);
                            }}
                        >
                            <Plus className="size-3.5" aria-hidden="true" />
                            {activeView !== null
                                ? `Update “${activeView.name}” or save as…`
                                : 'Save current view…'}
                        </Button>
                    </div>
                </PopoverContent>
            </Popover>

            <CrudDialog
                open={saveOpen}
                onOpenChange={(next) => {
                    setSaveOpen(next);

                    if (!next) {
                        setName('');
                    }
                }}
                title="Save the current view"
                description="Search, filters, sort and page size are stored. You can rename by saving under the same name."
                confirmLabel="Save view"
                onConfirm={submit}
                loading={controller.save.isPending}
                confirmDisabled={name.trim() === ''}
            >
                <div className="flex flex-col gap-1.5">
                    <label
                        htmlFor={`view-name-${uid}`}
                        className="text-sm font-medium text-surface-800 dark:text-surface-100"
                    >
                        View name
                    </label>
                    <Input
                        id={`view-name-${uid}`}
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        maxLength={60}
                        placeholder="Open tickets this week"
                        autoFocus
                    />
                    {controller.save.error ? (
                        <p role="alert" className="text-xs font-medium text-destructive">
                            {controller.save.error}
                        </p>
                    ) : null}
                </div>
            </CrudDialog>

            <CrudDialog
                open={deleteTarget !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleteTarget(null);
                    }
                }}
                title="Delete this view?"
                description="The saved view is removed. The records it filtered stay untouched."
                confirmLabel="Delete view"
                confirmVariant="destructive"
                loading={controller.remove.isPending}
                onConfirm={() => {
                    if (deleteTarget !== null) {
                        controller.remove.mutate(deleteTarget.uuid, {
                            onSuccess: () => setDeleteTarget(null),
                        });
                    }
                }}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    “{deleteTarget?.name ?? ''}” can be saved again at any time.
                </p>
                {controller.remove.error ? (
                    <p role="alert" className="mt-2 text-xs font-medium text-destructive">
                        {controller.remove.error}
                    </p>
                ) : null}
            </CrudDialog>
        </>
    );
}
