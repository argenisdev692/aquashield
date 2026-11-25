/**
 * CommandPalette — ⌘/Ctrl-K search overlay.
 *
 * Ported from the Vue CommandPalette: a Base UI dialog (portal, backdrop, focus
 * trap and Esc handling ride along for free) styled after the Vue screen — a
 * 40rem panel anchored at the top, grouped command list with roving keyboard
 * navigation, empty state and footer hints.
 */
import { useEffect, useId, useRef, useState } from 'react';
import { Search } from 'lucide-react';
import { Dialog as DialogPrimitive } from '@base-ui/react/dialog';
import { cn } from '@/lib/utils';
import type { Command } from '@/lib/commands';

interface CommandPaletteProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    commands: readonly Command[];
}

const KBD =
    'inline-flex h-5 min-w-5 items-center justify-center rounded border border-surface-200 bg-surface-50 px-1 font-sans text-[10px] font-medium text-surface-600 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300';

export default function CommandPalette({ open, onOpenChange, commands }: CommandPaletteProps) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listId = useId();
    const inputId = useId();
    const listRef = useRef<HTMLDivElement>(null);

    const needle = query.trim().toLowerCase();
    const filtered =
        needle === ''
            ? commands
            : commands.filter((command) =>
                  `${command.label} ${command.keywords ?? ''}`.toLowerCase().includes(needle),
              );

    const buckets = new Map<string, { command: Command; index: number }[]>();

    filtered.forEach((command, index) => {
        const bucket = buckets.get(command.group) ?? [];
        bucket.push({ command, index });
        buckets.set(command.group, bucket);
    });

    const groups = [...buckets.entries()].map(([label, items]) => ({ label, items }));

    useEffect(() => {
        if (!open) {
            setQuery('');
            setActive(0);
        }
    }, [open]);

    useEffect(() => {
        setActive(0);
    }, [query]);

    /*
     * ARIA 1.2 puts focus in the input and moves the *active option* with
     * `aria-activedescendant` — the browser does not scroll it for us. There
     * are up to 17 commands on the dashboard and the list is capped at 20rem
     * tall, so ArrowDown past the fold used to select a row the user could not
     * see (and Enter then ran it). `block: 'nearest'` scrolls the popup only.
     */
    useEffect(() => {
        listRef.current
            ?.querySelector<HTMLElement>(`[data-index="${active}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [active, query]);

    const close = (): void => {
        onOpenChange(false);
    };

    const run = (command: Command | undefined): void => {
        if (!command) {
            return;
        }

        close();
        command.run();
    };

    const move = (step: number): void => {
        const total = filtered.length;

        if (total === 0) {
            return;
        }

        setActive((current) => (current + step + total) % total);
    };

    const onKeydown = (event: React.KeyboardEvent): void => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(-1);
            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            setActive(0);
            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            setActive(Math.max(filtered.length - 1, 0));
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            run(filtered[active]);
            return;
        }
    };

    const optionId = (index: number): string => `${listId}-option-${index}`;

    const activeOptionId = filtered.length === 0 ? undefined : optionId(active);

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Backdrop className="fixed inset-0 z-50 bg-aq-overlay/50 backdrop-blur-sm" />
                <DialogPrimitive.Popup
                    data-slot="command-palette"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Command palette"
                    className="fixed top-1/2 left-1/2 z-50 max-h-[80vh] w-[min(40rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl border border-surface-200 bg-surface-0 text-surface-700 shadow-2xl outline-none transition-[opacity,transform] duration-150 ease-out data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-0"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Command palette
                    </DialogPrimitive.Title>
                    <DialogPrimitive.Description className="sr-only">
                        Search for a command or destination
                    </DialogPrimitive.Description>

                    <div className="flex items-center gap-3 border-b border-surface-200 px-4 py-3 dark:border-surface-700">
                        <Search className="text-sm text-surface-500" aria-hidden="true" />
                        <input
                            id={inputId}
                            type="text"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls={listId}
                            aria-activedescendant={activeOptionId}
                            aria-autocomplete="list"
                            aria-label="Search commands"
                            placeholder="Search commands…"
                            autoComplete="off"
                            spellCheck={false}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={onKeydown}
                            className="h-12 w-full bg-transparent text-sm text-surface-900 outline-none transition-colors duration-150 placeholder:text-surface-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus dark:text-surface-0 dark:placeholder:text-surface-400"
                        />
                        <kbd className={KBD}>esc</kbd>
                    </div>

                    <div
                        ref={listRef}
                        id={listId}
                        role="listbox"
                        aria-label="Commands"
                        className="max-h-80 overflow-y-auto p-2"
                    >
                        {filtered.length === 0 && (
                            <p
                                role="presentation"
                                className="px-3 py-8 text-center text-sm text-surface-500 dark:text-surface-300"
                            >
                                No commands match “{query}”.
                            </p>
                        )}

                        {groups.map((group) => (
                            <div key={group.label} role="group" aria-label={group.label}>
                                <p
                                    aria-hidden="true"
                                    className="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-[0.14em] text-surface-500 uppercase dark:text-surface-300"
                                >
                                    {group.label}
                                </p>
                                {group.items.map(({ command, index }) => {
                                    const isActive = index === active;

                                    return (
                                        <div
                                            key={command.id}
                                            id={optionId(index)}
                                            data-index={index}
                                            role="option"
                                            aria-selected={isActive}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors duration-100 motion-reduce:transition-none outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus',
                                                isActive
                                                    ? 'bg-surface-100 text-surface-900 dark:bg-surface-800 dark:text-surface-0'
                                                    : 'text-surface-700 dark:text-surface-200',
                                            )}
                                            onMouseEnter={() => setActive(index)}
                                            onClick={() => run(command)}
                                        >
                                            <Search
                                                className="size-4 shrink-0 text-aq-accent"
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">{command.label}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>

                    <div className="flex items-center gap-4 border-t border-surface-200 px-4 py-2.5 text-[11px] text-surface-500 dark:border-surface-700 dark:text-surface-300">
                        <span className="flex items-center gap-1">
                            <kbd className={KBD}>↑</kbd>
                            <kbd className={KBD}>↓</kbd>
                            navigate
                        </span>
                        <span className="flex items-center gap-1">
                            <kbd className={KBD}>↵</kbd>
                            select
                        </span>
                        <span className="flex items-center gap-1">
                            <kbd className={KBD}>esc</kbd>
                            close
                        </span>
                    </div>
                </DialogPrimitive.Popup>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
