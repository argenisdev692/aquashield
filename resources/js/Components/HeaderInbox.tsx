/**
 * HeaderInbox — notifications / messages indicator in header.
 *
 * The bell and the envelope share one panel recipe (Base UI Popover): same
 * shell, same empty state. The list is data-driven and defaults to empty: this
 * app has no notifications or messages table yet, so the panel shows an honest
 * empty state rather than a fabricated unread count. When a backend starts
 * passing `items`, the badge and rows appear with no layout change.
 */
import { useId, useState } from 'react';
import { Bell, Mail } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Popover, PopoverContent, PopoverTitle, PopoverTrigger } from '@/Components/ui/popover';

interface InboxItem {
    id: string;
    title: string;
    body?: string;
    /** Pre-formatted relative time, e.g. "2 min ago". */
    time: string;
    /** In-app destination; omit for a non-navigable entry. */
    href?: string;
    unread?: boolean;
}

interface HeaderInboxProps {
    kind: 'notifications' | 'messages';
    items?: readonly InboxItem[];
}

export default function HeaderInbox({ kind, items = [] }: HeaderInboxProps) {
    const [open, setOpen] = useState(false);
    const triggerId = useId();

    const copy =
        kind === 'notifications'
            ? {
                  label: 'Notifications',
                  icon: Bell,
                  emptyTitle: "You're all caught up",
                  emptyBody: 'Pipeline alerts, mentions and task reminders will land here.',
              }
            : {
                  label: 'Messages',
                  icon: Mail,
                  emptyTitle: 'No messages yet',
                  emptyBody: 'Client replies and teammate threads will land here.',
              };

    const Icon = copy.icon;
    const unread = items.filter((item) => item.unread).length;
    const badge = unread > 9 ? '9+' : String(unread);

    return (
        <Popover open={open} onOpenChange={(next) => setOpen(next)} triggerId={triggerId}>
            <PopoverTrigger
                id={triggerId}
                type="button"
                className="relative grid size-9 place-items-center rounded-lg text-surface-600 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
                aria-label={unread > 0 ? `${copy.label}, ${unread} unread` : copy.label}
            >
                <Icon className="size-4" aria-hidden="true" />
                {unread > 0 && (
                    <span
                        className="absolute top-1 right-1 grid size-4 place-items-center rounded-full bg-aq-accent text-[10px] font-semibold text-aq-void"
                        aria-hidden="true"
                    >
                        {badge}
                    </span>
                )}
            </PopoverTrigger>
            {/* The trigger sits at the right edge of the header, so a centred
                panel hangs past it and the collision guard pushes it back in —
                which reads as a panel detached from its bell and floating to
                the right. Align the panel's end edge with the trigger's end
                edge instead; the 320px body then opens inward over the page. */}
            <PopoverContent align="end" className="w-[min(20rem,calc(100vw-2rem))] p-0">
                <div className="flex items-center justify-between gap-3 px-3 pt-3 pb-2">
                    <PopoverTitle className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                        {copy.label}
                    </PopoverTitle>
                    {items.length > 0 && (
                        <span className="text-xs text-surface-500 dark:text-surface-400">
                            {items.length}
                        </span>
                    )}
                </div>
                {items.length > 0 ? (
                    <ul className="max-h-80 overflow-y-auto px-2 pb-2">
                        {items.map((item) => (
                            <li key={item.id}>
                                {item.href ? (
                                    <Link
                                        href={item.href}
                                        onClick={() => setOpen(false)}
                                        className="flex cursor-pointer gap-3 rounded-lg px-2 py-2 transition-colors duration-150 hover:bg-surface-100 motion-reduce:transition-none dark:hover:bg-surface-800"
                                    >
                                        <span
                                            className={`mt-1.5 size-1.5 shrink-0 rounded-full ${
                                                item.unread
                                                    ? 'bg-aq-accent'
                                                    : 'bg-surface-300 dark:bg-surface-600'
                                            }`}
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-surface-900 dark:text-surface-0">
                                                {item.title}
                                            </span>
                                            {item.body && (
                                                <span className="mt-0.5 block text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                                                    {item.body}
                                                </span>
                                            )}
                                            <span className="mt-1 block text-[11px] text-surface-500 dark:text-surface-400">
                                                {item.time}
                                            </span>
                                        </span>
                                    </Link>
                                ) : (
                                    <div
                                        className="flex cursor-pointer gap-3 rounded-lg px-2 py-2 transition-colors duration-150 hover:bg-surface-100 motion-reduce:transition-none dark:hover:bg-surface-800"
                                        onClick={() => setOpen(false)}
                                    >
                                        <span
                                            className={`mt-1.5 size-1.5 shrink-0 rounded-full ${
                                                item.unread
                                                    ? 'bg-aq-accent'
                                                    : 'bg-surface-300 dark:bg-surface-600'
                                            }`}
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-surface-900 dark:text-surface-0">
                                                {item.title}
                                            </span>
                                            {item.body && (
                                                <span className="mt-0.5 block text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                                                    {item.body}
                                                </span>
                                            )}
                                            <span className="mt-1 block text-[11px] text-surface-500 dark:text-surface-400">
                                                {item.time}
                                            </span>
                                        </span>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="mx-2 mb-2 rounded-lg border border-dashed border-surface-300 px-4 py-6 text-center dark:border-surface-700">
                        <span className="mx-auto grid size-9 place-items-center rounded-full bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400">
                            <Icon className="size-3.5" aria-hidden="true" />
                        </span>
                        <p className="mt-3 text-sm font-medium text-surface-900 dark:text-surface-0">
                            {copy.emptyTitle}
                        </p>
                        <p className="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                            {copy.emptyBody}
                        </p>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
