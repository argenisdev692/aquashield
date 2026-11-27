/**
 * LeadKanban — the server-grouped pipeline board.
 *
 * One implementation for the Leads board page and the Dashboard pipeline view.
 * Columns arrive already grouped and counted by the server; the browser never
 * re-slices a paginated response. A drag or the card selector posts the stage
 * change and the transition map is enforced on the server.
 *
 * The card is an operational summary, not a detail page: name, reachability,
 * source, age and the one date that changes the next action. Everything else
 * lives behind the lead itself, so the board stays scannable at a glance.
 *
 * Two layouts: `scroll` (the Leads board) keeps every stage at card width in a
 * horizontal scroller; `fit` (the dashboard) stretches the four primary stages
 * across the panel and summarises the remaining stages as counted links into
 * the table view, so the pipeline reads at a glance without a sideways scroll.
 */
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { CalendarClock, MapPin, Phone } from 'lucide-react';
import { Tag } from '@/Components/ui/tag';
import { formatDateTime } from '@/lib/date';
import { buildTableUrl } from '@/lib/table';
import { cn, humanize } from '@/lib/utils';

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
    /** `fit` stretches the primary stages and summarises the rest. */
    variant?: 'scroll' | 'fit';
}

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

/** The stages the fitted dashboard layout stretches across the panel. */
const FIT_PRIMARY: readonly string[] = ['new', 'contacted', 'qualified', 'won'];

const stageTableUrl = (status: string): string =>
    buildTableUrl(
        '/leads',
        { page: 1, perPage: 15, search: '', sort: null, filters: { status } },
        { paginate: false },
    );

export default function LeadKanban({
    columns,
    transitions,
    canUpdate,
    canView = true,
    errors,
    onSelect,
    variant = 'scroll',
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

    const primaryColumns =
        variant === 'fit'
            ? FIT_PRIMARY.map((status) =>
                  columns.find((column) => column.status === status),
              ).filter((column): column is LeadBoardColumn => column !== undefined)
            : columns;
    const secondaryColumns =
        variant === 'fit' ? columns.filter((column) => !FIT_PRIMARY.includes(column.status)) : [];

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

            {/*
             * A scroll region must be focusable so a keyboard user can scroll
             * the pipeline without a pointer (WCAG 2.1.1; axe
             * `scrollable-region-focusable`). The matching lint exception lives
             * in `.oxlintrc.json` with the same reason.
             */}
            <div
                role="region"
                aria-label="Lead pipeline board"
                tabIndex={0}
                className="aq-scroll-quiet flex gap-3 overflow-x-auto pb-3 transition-shadow duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none"
            >
                {primaryColumns.map((column) => {
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
                                'flex flex-col rounded-xl border p-2 transition-colors duration-150 motion-reduce:transition-none',
                                variant === 'fit' ? 'min-w-60 flex-1' : 'w-72 shrink-0',
                                'border-surface-200/80 bg-surface-100/60 dark:border-surface-800 dark:bg-surface-900/40',
                                isOver
                                    ? 'border-aq-accent/60 bg-aq-accent/5 ring-2 ring-aq-accent/30 dark:bg-aq-accent/5'
                                    : '',
                            )}
                        >
                            <header className="flex items-center justify-between gap-2 px-1.5 pt-1 pb-2.5">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            columnAccent[column.status] ?? 'bg-surface-400',
                                        )}
                                    />
                                    <h2 className="truncate text-[13px] font-semibold text-surface-900 dark:text-surface-0">
                                        {column.label}
                                    </h2>
                                </div>
                                <span className="min-w-6 rounded-full bg-surface-200/80 px-1.5 py-px text-center text-[11px] font-semibold text-surface-600 tabular-nums dark:bg-surface-800 dark:text-surface-300">
                                    {column.total}
                                </span>
                            </header>

                            {column.leads.length > 0 && (
                                <ol className="flex flex-col gap-2">
                                    {column.leads.map((card) => (
                                        <li key={card.uuid}>
                                            <article
                                                draggable={canUpdate}
                                                onDragStart={(event) => {
                                                    setDragging(card);
                                                    event.dataTransfer.effectAllowed = 'move';
                                                    event.dataTransfer.setData(
                                                        'text/plain',
                                                        card.uuid,
                                                    );
                                                }}
                                                onDragEnd={() => {
                                                    setDragging(null);
                                                    setOverStatus(null);
                                                }}
                                                className={cn(
                                                    'group/card rounded-lg border bg-surface-0 p-3 shadow-sm transition-[opacity,box-shadow,border-color] duration-150 dark:bg-surface-900',
                                                    'border-surface-200 hover:border-aq-accent/40 dark:border-surface-700 dark:hover:border-aq-accent/35',
                                                    canUpdate
                                                        ? 'cursor-grab active:cursor-grabbing'
                                                        : '',
                                                    dragging?.uuid === card.uuid
                                                        ? 'opacity-40'
                                                        : '',
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
                                                                className="min-w-0 truncate text-[13px] leading-snug font-semibold text-surface-900 transition-colors duration-150 hover:text-aq-accent-text hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus dark:text-surface-0 dark:hover:text-aq-accent-text"
                                                            >
                                                                {card.full_name}
                                                            </Link>
                                                        ) : (
                                                            <span className="min-w-0 truncate text-[13px] leading-snug font-semibold text-surface-900 dark:text-surface-0">
                                                                {card.full_name}
                                                            </span>
                                                        )
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => onSelect(card)}
                                                            className="min-w-0 truncate text-start text-[13px] leading-snug font-semibold text-surface-900 transition-colors duration-150 hover:text-aq-accent-text hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus dark:text-surface-0 dark:hover:text-aq-accent-text"
                                                        >
                                                            {card.full_name}
                                                        </button>
                                                    )}

                                                    <span className="flex shrink-0 items-center gap-1">
                                                        {card.is_spam && (
                                                            <Tag
                                                                variant="outline"
                                                                className="px-1.5 py-px text-[10px]"
                                                            >
                                                                Spam
                                                            </Tag>
                                                        )}
                                                        {card.is_emergency && (
                                                            <Tag
                                                                variant="destructive"
                                                                className="px-1.5 py-px text-[10px]"
                                                            >
                                                                Emergency
                                                            </Tag>
                                                        )}
                                                    </span>
                                                </div>

                                                <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-surface-600 dark:text-surface-300">
                                                    <span className="inline-flex items-center gap-1">
                                                        <Phone
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="tabular-nums">
                                                            {card.phone ?? 'No phone'}
                                                        </span>
                                                    </span>
                                                    <span aria-hidden="true">·</span>
                                                    <span className="inline-flex min-w-0 items-center gap-1">
                                                        <MapPin
                                                            className="size-3 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="truncate">
                                                            {[card.city, card.state]
                                                                .filter(Boolean)
                                                                .join(', ') ||
                                                                humanize(card.region ?? '')}
                                                        </span>
                                                    </span>
                                                </p>

                                                <div className="mt-2.5 flex items-center justify-between gap-2">
                                                    <span className="inline-flex min-w-0 items-center gap-1.5 rounded-md bg-surface-100 px-1.5 py-0.5 text-[10px] font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                                        <span
                                                            aria-hidden="true"
                                                            className="size-1 shrink-0 rounded-full bg-aq-accent/70"
                                                        />
                                                        <span className="truncate">
                                                            {humanize(card.source)}
                                                        </span>
                                                    </span>
                                                    <span
                                                        className="shrink-0 text-[10px] text-surface-600 tabular-nums dark:text-surface-300"
                                                        title={`Created ${ageInDays(card.created_at)}`}
                                                    >
                                                        {ageInDays(card.created_at)}
                                                    </span>
                                                </div>

                                                {card.inspection_at !== null && (
                                                    <p className="mt-2.5 flex items-center gap-1.5 rounded-md border border-aq-accent/15 bg-aq-accent/[0.04] px-2 py-1 text-[11px] font-medium text-surface-700 tabular-nums dark:border-aq-accent/20 dark:bg-aq-accent/[0.06] dark:text-surface-200">
                                                        <CalendarClock
                                                            className="size-3 shrink-0 text-aq-accent"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="truncate">
                                                            {formatDateTime(
                                                                card.inspection_at,
                                                                'en-US',
                                                                {
                                                                    dateStyle: 'medium',
                                                                    timeStyle: 'short',
                                                                },
                                                            )}
                                                        </span>
                                                    </p>
                                                )}

                                                {canUpdate &&
                                                    (transitions[card.status] ?? []).length > 0 && (
                                                        <label className="mt-2.5 flex flex-col gap-1 border-t border-surface-100 pt-2 dark:border-surface-800">
                                                            <span className="sr-only">
                                                                Move {card.full_name} to another
                                                                stage
                                                            </span>
                                                            <select
                                                                value=""
                                                                disabled={movingUuid !== null}
                                                                onChange={(event) => {
                                                                    if (event.target.value !== '') {
                                                                        move(
                                                                            card,
                                                                            event.target.value,
                                                                        );
                                                                    }
                                                                }}
                                                                className="h-7 w-full rounded-md border border-transparent bg-surface-50 px-2 text-[11px] font-medium text-surface-600 transition-colors duration-150 hover:border-surface-200 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus disabled:opacity-60 dark:bg-surface-800/60 dark:text-surface-300 dark:hover:border-surface-700 dark:hover:bg-surface-800 motion-reduce:transition-none"
                                                            >
                                                                <option value="">Move to…</option>
                                                                {(
                                                                    transitions[card.status] ?? []
                                                                ).map((status) => (
                                                                    <option
                                                                        key={status}
                                                                        value={status}
                                                                    >
                                                                        {humanize(status)}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </label>
                                                    )}
                                            </article>
                                        </li>
                                    ))}
                                </ol>
                            )}

                            {column.leads.length === 0 && (
                                <p className="rounded-lg border border-dashed border-surface-300/80 px-3 py-6 text-center text-[11px] leading-relaxed text-surface-600 dark:border-surface-700 dark:text-surface-300">
                                    {droppable ? (
                                        'Drop a card here'
                                    ) : (
                                        <>
                                            <span className="block font-medium text-surface-600 dark:text-surface-200">
                                                No leads in this stage
                                            </span>
                                            <span>
                                                {canUpdate
                                                    ? 'Drag a lead here when it moves forward.'
                                                    : 'New leads will appear here.'}
                                            </span>
                                        </>
                                    )}
                                </p>
                            )}

                            {column.total > column.leads.length && (
                                <p className="px-2 pt-2 text-center text-[10px] text-surface-600 dark:text-surface-300">
                                    Showing the newest {column.leads.length} of {column.total}. Open
                                    the table view to filter the rest.
                                </p>
                            )}
                        </section>
                    );
                })}

                {secondaryColumns.length > 0 && (
                    <section
                        aria-label="Other stages"
                        className="flex w-48 shrink-0 flex-col rounded-xl border border-surface-200/80 bg-surface-100/60 p-2 dark:border-surface-800 dark:bg-surface-900/40"
                    >
                        <header className="px-1.5 pt-1 pb-2.5 text-[13px] font-semibold text-surface-900 dark:text-surface-0">
                            Other stages
                        </header>

                        <ul className="flex flex-col gap-1">
                            {secondaryColumns.map((column) => (
                                <li key={column.status}>
                                    <Link
                                        href={stageTableUrl(column.status)}
                                        className="flex items-center justify-between gap-2 rounded-md px-1.5 py-1 text-[11px] font-medium text-surface-600 transition-colors duration-150 hover:bg-surface-0 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                                    >
                                        <span className="flex min-w-0 items-center gap-2">
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'size-2 shrink-0 rounded-full',
                                                    columnAccent[column.status] ?? 'bg-surface-400',
                                                )}
                                            />
                                            <span className="truncate">{column.label}</span>
                                        </span>
                                        <span className="tabular-nums">{column.total}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <p className="px-1.5 pt-2 text-[10px] text-surface-600 dark:text-surface-300">
                            Open a stage in the table view.
                        </p>
                    </section>
                )}
            </div>
        </div>
    );
}
