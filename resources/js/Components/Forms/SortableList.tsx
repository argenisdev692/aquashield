/**
 * SortableList — generic reorderable list for a client-owned ordered
 * collection (pipeline stages, line items, saved views).
 *
 * Reordering is deliberate here: the array is the draft the user edits and
 * submits, not a page of server rows. Never use this on a paginated
 * server-owned list — that belongs to the DataTable and the URL state.
 *
 * Native HTML5 drag is the pointer path; the grip is a real button that also
 * responds to ArrowUp / ArrowDown / Home / End, and the two arrow buttons give
 * touch users a non-drag path. A polite live region announces every move.
 */
import { useId, useState } from 'react';
import { ArrowDown, ArrowUp, GripVertical } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface SortableItem {
    id: string | number;
}

interface SortableListProps<T extends SortableItem> {
    items: T[];
    onChange: (items: T[]) => void;
    renderItem: (item: T, index: number) => React.ReactNode;
    itemLabel?: (item: T) => string;
    disabled?: boolean;
    handle?: boolean;
    emptyText?: string;
    label?: string;
    className?: string;
}

export default function SortableList<T extends SortableItem>({
    items,
    onChange,
    renderItem,
    itemLabel,
    disabled = false,
    handle = true,
    emptyText = 'Nothing to sort yet.',
    label = 'Sortable list',
    className,
}: SortableListProps<T>) {
    const [draggedId, setDraggedId] = useState<string | number | null>(null);
    const [overId, setOverId] = useState<string | number | null>(null);
    const [announcement, setAnnouncement] = useState('');
    const instructionsId = useId();

    const nameOf = (item: T): string => itemLabel?.(item) ?? `Item ${items.indexOf(item) + 1}`;

    const move = (from: number, to: number): void => {
        if (from === to || to < 0 || to >= items.length) {
            return;
        }

        const next = [...items];
        const [moved] = next.splice(from, 1);

        if (moved === undefined) {
            return;
        }

        next.splice(to, 0, moved);
        onChange(next);
        setAnnouncement(`Moved ${nameOf(moved)} to position ${to + 1} of ${next.length}.`);
    };

    const moveById = (id: string | number, to: number): void => {
        const from = items.findIndex((item) => item.id === id);
        move(from, to);
    };

    return (
        <div className={cn('w-full', className)}>
            <div role="list" aria-label={label} className="flex flex-col gap-2">
                {items.map((item, index) => {
                    const isDragged = draggedId === item.id;
                    const isOver = overId === item.id;

                    return (
                        <div
                            key={item.id}
                            role="listitem"
                            draggable={!disabled && !handle}
                            onDragStart={
                                handle
                                    ? undefined
                                    : (event) => {
                                          setDraggedId(item.id);
                                          event.dataTransfer.effectAllowed = 'move';
                                          event.dataTransfer.setData('text/plain', String(item.id));
                                      }
                            }
                            onDragOver={(event) => {
                                if (disabled || draggedId === null) {
                                    return;
                                }

                                event.preventDefault();
                                event.dataTransfer.dropEffect = 'move';

                                if (overId !== item.id) {
                                    setOverId(item.id);
                                }
                            }}
                            onDrop={(event) => {
                                if (disabled || draggedId === null) {
                                    return;
                                }

                                event.preventDefault();
                                moveById(draggedId, index);
                                setDraggedId(null);
                                setOverId(null);
                            }}
                            onDragEnd={() => {
                                setDraggedId(null);
                                setOverId(null);
                            }}
                            className={cn(
                                'flex items-center gap-3 rounded-md border border-surface-300 bg-surface-0 px-3 py-2 transition-colors duration-150',
                                'dark:border-surface-700 dark:bg-surface-900',
                                isDragged && 'opacity-50',
                                isOver && 'border-aq-accent',
                                disabled && 'opacity-50',
                            )}
                        >
                            <button
                                type="button"
                                draggable={!disabled && handle}
                                disabled={disabled}
                                aria-label={`Reorder ${nameOf(item)}`}
                                aria-describedby={instructionsId}
                                title="Drag, or press Arrow Up / Arrow Down"
                                className="cursor-grab rounded-sm text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent active:cursor-grabbing disabled:cursor-not-allowed dark:text-surface-300 dark:hover:text-surface-0"
                                onDragStart={(event) => {
                                    if (!handle) {
                                        return;
                                    }

                                    setDraggedId(item.id);
                                    event.dataTransfer.effectAllowed = 'move';
                                    event.dataTransfer.setData('text/plain', String(item.id));
                                }}
                                onKeyDown={(event) => {
                                    if (disabled) {
                                        return;
                                    }

                                    if (event.key === 'ArrowUp') {
                                        event.preventDefault();
                                        move(index, index - 1);
                                    }

                                    if (event.key === 'ArrowDown') {
                                        event.preventDefault();
                                        move(index, index + 1);
                                    }

                                    if (event.key === 'Home') {
                                        event.preventDefault();
                                        move(index, 0);
                                    }

                                    if (event.key === 'End') {
                                        event.preventDefault();
                                        move(index, items.length - 1);
                                    }
                                }}
                            >
                                <GripVertical className="size-4" aria-hidden="true" />
                            </button>

                            <div className="min-w-0 flex-1">{renderItem(item, index)}</div>

                            <div className="flex shrink-0 items-center gap-0.5">
                                <button
                                    type="button"
                                    disabled={disabled || index === 0}
                                    aria-label={`Move ${nameOf(item)} up`}
                                    className="flex size-7 items-center justify-center rounded-sm text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:pointer-events-none disabled:opacity-30 dark:text-surface-300 dark:hover:text-surface-0"
                                    onClick={() => move(index, index - 1)}
                                >
                                    <ArrowUp className="size-3.5" aria-hidden="true" />
                                </button>

                                <button
                                    type="button"
                                    disabled={disabled || index === items.length - 1}
                                    aria-label={`Move ${nameOf(item)} down`}
                                    className="flex size-7 items-center justify-center rounded-sm text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:pointer-events-none disabled:opacity-30 dark:text-surface-300 dark:hover:text-surface-0"
                                    onClick={() => move(index, index + 1)}
                                >
                                    <ArrowDown className="size-3.5" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            {items.length === 0 && (
                <p className="py-6 text-center text-sm text-surface-500 dark:text-surface-300">
                    {emptyText}
                </p>
            )}

            <p id={instructionsId} className="sr-only">
                Press Arrow Up or Arrow Down to move an item. Home and End move it to the start or
                end of the list.
            </p>

            <p className="sr-only" aria-live="polite">
                {announcement}
            </p>
        </div>
    );
}
