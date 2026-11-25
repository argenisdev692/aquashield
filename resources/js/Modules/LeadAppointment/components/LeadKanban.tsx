/**
 * LeadKanban — the server-grouped pipeline board.
 *
 * One implementation for the Leads board page and the Dashboard pipeline tab.
 * Columns arrive already grouped and counted by the server; the browser never
 * re-slices a paginated response. A drag or the card selector posts the stage
 * change and the transition map is enforced on the server.
 */
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { CalendarClock, Phone } from 'lucide-react';
import { Tag } from '@/Components/ui/tag';
import { formatDateTime } from '@/lib/date';
import { cn } from '@/lib/utils';

type LeadCard = Modules.LeadAppointment.Application.Dtos.LeadCardData;
type LeadBoardColumn = Modules.LeadAppointment.Application.Dtos.LeadBoardColumnData;

interface LeadKanbanProps {
    columns: LeadBoardColumn[];
    transitions: Record<string, string[]>;
    canUpdate: boolean;
    canView?: boolean;
    errors?: Record<string, string>;
    /** When provided, the card title opens a drawer instead of navigating. */
    onSelect?: (lead: LeadCard) => void;
}

const humanize = (value: string): string => {
    const label = value.replace(/_/g, ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
};

const ageInDays = (value: string | null): string => {
    if (value === null) {
        return '';
    }

    const created = new Date(value);

    if (Number.isNaN(created.getTime())) {
        return '';
    }

    const days = Math.max(0, Math.floor((Date.now() - created.getTime()) / 86_400_000));

    return days === 0 ? 'today' : `${days}d`;
};

const columnAccent: Record<string, string> = {
    new: 'bg-aq-accent',
    contacted: 'bg-aq-warning',
    qualified: 'bg-aq-warning',
    inspection_scheduled: 'bg-aq-success',
    won: 'bg-aq-success',
    lost: 'bg-aq-danger',
    nurture: 'bg-surface-400',
};

export default function LeadKanban({
    columns,
    transitions,
    canUpdate,
    canView = true,
    errors,
    onSelect,
}: LeadKanbanProps) {
    const [dragging, setDragging] = useState<LeadCard | null>(null);
    const [overStatus, setOverStatus] = useState<string | null>(null);
    const [movingUuid, setMovingUuid] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const visibleError = error ?? errors?.status ?? null;

    const canMove = (card: LeadCard, status: string): boolean =>
        canUpdate && (transitions[card.status] ?? []).includes(status);

    const move = (card: LeadCard, status: string): void => {
        if (!canMove(card, status) || card.status === status || movingUuid !== null) {
            return;
        }

        setError(null);
        setMovingUuid(card.uuid);

        router.post(
            `/leads/${card.uuid}/status`,
            { status, note: null },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (nextErrors) => {
                    setError(nextErrors.status ?? 'That stage change is not allowed.');
                },
                onFinish: () => {
                    setMovingUuid(null);
                    setDragging(null);
                    setOverStatus(null);
                },
            },
        );
    };

    return (
        <div className="flex flex-col gap-3">
            {visibleError !== null && (
                <p
                    role="alert"
                    className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    {visibleError}
                </p>
            )}

            <div className="flex gap-3 overflow-x-auto pb-3">
                {columns.map((column) => {
                    const droppable = dragging !== null && canMove(dragging, column.status);
                    const isOver = droppable && overStatus === column.status;

                    return (
                        <section
                            key={column.status}
                            aria-label={`${column.label} stage`}
                            onDragOver={(event) => {
                                if (droppable) {
                                    event.preventDefault();
                                    event.dataTransfer.dropEffect = 'move';
                                }
                            }}
                            onDragEnter={() => {
                                if (droppable) {
                                    setOverStatus(column.status);
                                }
                            }}
                            onDragLeave={(event) => {
                                if (event.currentTarget.contains(event.relatedTarget as Node)) {
                                    return;
                                }

                                setOverStatus((current) =>
                                    current === column.status ? null : current,
                                );
                            }}
                            onDrop={(event) => {
                                event.preventDefault();

                                if (dragging !== null) {
                                    move(dragging, column.status);
                                }
                            }}
                            className={cn(
                                'flex min-w-72 shrink-0 flex-col rounded-xl border p-2 transition-colors duration-150 motion-reduce:transition-none',
                                'border-surface-200 bg-surface-50 dark:border-surface-800 dark:bg-surface-800/40',
                                isOver
                                    ? 'border-aq-accent/60 bg-surface-100 ring-2 ring-aq-accent/40 dark:bg-surface-800/80'
                                    : '',
                            )}
                        >
                            <header className="flex items-center justify-between gap-2 px-2 pb-2 pt-1">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            columnAccent[column.status] ?? 'bg-surface-400',
                                        )}
                                    />
                                    <h2 className="truncate text-sm font-semibold text-surface-900 dark:text-surface-0">
                                        {column.label}
                                    </h2>
                                </div>
                                <span className="rounded-full bg-surface-200 px-2 py-px text-[11px] font-semibold tabular-nums text-surface-600 dark:bg-surface-700 dark:text-surface-300">
                                    {column.total}
                                </span>
                            </header>

                            <ol className="flex min-h-28 flex-col gap-2">
                                {column.leads.map((card) => (
                                    <li key={card.uuid}>
                                        <article
                                            draggable={canUpdate}
                                            onDragStart={(event) => {
                                                setDragging(card);
                                                event.dataTransfer.effectAllowed = 'move';
                                                event.dataTransfer.setData('text/plain', card.uuid);
                                            }}
                                            onDragEnd={() => {
                                                setDragging(null);
                                                setOverStatus(null);
                                            }}
                                            className={cn(
                                                'rounded-lg border bg-surface-0 p-3 shadow-sm transition-[opacity,box-shadow,border-color] duration-150 dark:bg-surface-900',
                                                'border-surface-200 dark:border-surface-700',
                                                canUpdate
                                                    ? 'cursor-grab active:cursor-grabbing'
                                                    : '',
                                                dragging?.uuid === card.uuid ? 'opacity-40' : '',
                                                card.is_emergency
                                                    ? 'border-s-2 border-s-aq-danger'
                                                    : '',
                                                movingUuid === card.uuid ? 'opacity-60' : '',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                {onSelect === undefined ? (
                                                    canView ? (
                                                        <Link
                                                            href={`/leads/${card.uuid}`}
                                                            className="min-w-0 truncate text-sm font-semibold text-surface-900 transition-colors duration-150 hover:text-aq-accent hover:underline dark:text-surface-0"
                                                        >
                                                            {card.full_name}
                                                        </Link>
                                                    ) : (
                                                        <span className="min-w-0 truncate text-sm font-semibold text-surface-900 dark:text-surface-0">
                                                            {card.full_name}
                                                        </span>
                                                    )
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() => onSelect(card)}
                                                        className="min-w-0 truncate text-start text-sm font-semibold text-surface-900 transition-colors duration-150 hover:text-aq-accent hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus dark:text-surface-0"
                                                    >
                                                        {card.full_name}
                                                    </button>
                                                )}
                                                {card.is_spam && <Tag variant="outline">Spam</Tag>}
                                                {card.is_emergency && (
                                                    <Tag variant="destructive">Emergency</Tag>
                                                )}
                                            </div>

                                            <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-surface-500 dark:text-surface-300">
                                                <span className="inline-flex items-center gap-1">
                                                    <Phone className="size-3" aria-hidden="true" />
                                                    <span className="tabular-nums">
                                                        {card.phone ?? 'No phone'}
                                                    </span>
                                                </span>
                                                <span aria-hidden="true">·</span>
                                                <span className="truncate">
                                                    {[card.city, card.state]
                                                        .filter(Boolean)
                                                        .join(', ') || humanize(card.region ?? '')}
                                                </span>
                                            </p>

                                            <p className="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs text-surface-500 dark:text-surface-300">
                                                <span>{humanize(card.source)}</span>
                                                <span className="tabular-nums">
                                                    {ageInDays(card.created_at)}
                                                </span>
                                            </p>

                                            {card.inspection_at !== null && (
                                                <p className="mt-2 inline-flex items-center gap-1 rounded-md bg-surface-100 px-1.5 py-0.5 text-xs tabular-nums text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                    <CalendarClock
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                    {formatDateTime(card.inspection_at, 'en-US', {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    })}
                                                </p>
                                            )}

                                            {canUpdate &&
                                                (transitions[card.status] ?? []).length > 0 && (
                                                    <label className="mt-2 flex flex-col gap-1">
                                                        <span className="sr-only">
                                                            Move {card.full_name} to another stage
                                                        </span>
                                                        <select
                                                            value=""
                                                            disabled={movingUuid !== null}
                                                            onChange={(event) => {
                                                                if (event.target.value !== '') {
                                                                    move(card, event.target.value);
                                                                }
                                                            }}
                                                            className="h-7 w-full rounded-md border border-surface-200 bg-surface-0 px-2 text-xs text-surface-700 transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus disabled:opacity-60 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200"
                                                        >
                                                            <option value="">Move to…</option>
                                                            {(transitions[card.status] ?? []).map(
                                                                (status) => (
                                                                    <option
                                                                        key={status}
                                                                        value={status}
                                                                    >
                                                                        {humanize(status)}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </label>
                                                )}
                                        </article>
                                    </li>
                                ))}
                            </ol>

                            {column.leads.length === 0 && (
                                <p className="rounded-lg border border-dashed border-surface-200 py-6 text-center text-xs text-surface-500 dark:border-surface-800 dark:text-surface-300">
                                    {droppable ? 'Drop a card here' : 'No leads in this stage'}
                                </p>
                            )}

                            {column.total > column.leads.length && (
                                <p className="px-2 pt-2 text-center text-[11px] text-surface-500 dark:text-surface-300">
                                    Showing the newest {column.leads.length} of {column.total}. Open
                                    the table view to filter the rest.
                                </p>
                            )}
                        </section>
                    );
                })}
            </div>
        </div>
    );
}
