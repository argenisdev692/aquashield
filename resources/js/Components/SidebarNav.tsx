/**
 * SidebarNav — the one navigation list, rendered identically by the desktop
 * rail and the mobile drawer. It owns no open/collapsed state and calls no
 * route: the shell passes the groups in and listens for `navigate`, so the same
 * list cannot drift between the two surfaces.
 *
 * Section headings are disclosures, not decoration: Workspace, Organization
 * and System fold away with a real `<button aria-expanded>` that controls the
 * `<ul>` of links (WAI-ARIA disclosure-navigation). Sections are folded by
 * default: the one that owns the current route opens, and a section the user
 * leaves open stays open across visits (`useSidebarSections`, persisted).
 * A single-destination group (Account) keeps a plain heading — an arrow that
 * hides one row is noise.
 *
 * Active state is never colour-only: a 2px accent bar, a tinted row and
 * `aria-current="page"` all fire together, and the row keeps its shape when the
 * rail collapses to icons (labels swap to `sr-only` and the tooltip takes over).
 */
import { useId } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    isActivePath,
    jumpToSection,
    sectionId,
    type NavGroup,
    type NavItem,
} from '@/lib/navigation';
import { useSidebarSections } from '@/lib/theme';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/Components/ui/tooltip';

interface SidebarNavProps {
    /** Groups from `@/lib/navigation`, already permission-filtered. */
    groups: readonly NavGroup[];
    /** Current route path, used for the active row. */
    currentPath: string;
    /** Collapsed rail mode: labels hide and tooltips appear. */
    collapsed?: boolean;
    /** Drawer-only: close the mobile sheet after a destination is picked. */
    onNavigate?: () => void;
}

const rowClass = (active: boolean, collapsed: boolean): string =>
    cn(
        'group relative flex h-10 items-center gap-3 rounded-lg text-sm font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none',
        collapsed ? 'justify-center px-0' : 'px-2.5',
        active
            ? 'bg-aq-accent/10 text-surface-900 transition-colors duration-150 dark:bg-aq-accent/15 dark:text-surface-0'
            : 'text-surface-600 hover:bg-surface-100 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0',
    );

export default function SidebarNav({
    groups,
    currentPath,
    collapsed = false,
    onNavigate,
}: SidebarNavProps) {
    const baseId = useId();
    const { sectionOverrides, setSectionOpen } = useSidebarSections();

    const active = (href: string): boolean => isActivePath(currentPath, href);

    const onActivate = (item: NavItem, event: React.MouseEvent): void => {
        if (item.anchor === true) {
            event.preventDefault();
            jumpToSection(sectionId(item.href));
        }

        onNavigate?.();
    };

    const renderItem = (item: NavItem, key: string) => {
        const isItemActive = active(item.href);
        const ariaCurrent = item.anchor === true ? undefined : isItemActive ? 'page' : undefined;

        const content = (
            <>
                {isItemActive && (
                    <span
                        aria-hidden="true"
                        className="aq-nav-indicator absolute inset-y-1.5 start-0 w-0.5 rounded-full bg-aq-accent"
                    />
                )}

                <item.icon
                    className={cn(
                        'h-4 w-4 shrink-0',
                        isItemActive
                            ? 'text-aq-accent-deep dark:text-aq-accent'
                            : 'text-surface-500 dark:text-surface-300',
                    )}
                    aria-hidden="true"
                />

                <span className={collapsed ? 'sr-only' : 'truncate'}>{item.label}</span>
            </>
        );

        const interactive =
            item.anchor === true ? (
                <a
                    href={item.href}
                    onClick={(event) => onActivate(item, event)}
                    aria-current={ariaCurrent}
                    className={rowClass(isItemActive, collapsed)}
                >
                    {content}
                </a>
            ) : (
                <Link
                    href={item.href}
                    onClick={(event) => onActivate(item, event)}
                    aria-current={ariaCurrent}
                    className={rowClass(isItemActive, collapsed)}
                >
                    {content}
                </Link>
            );

        return (
            <li key={key} className="list-none">
                {collapsed ? (
                    <Tooltip>
                        <TooltipTrigger render={interactive} />
                        <TooltipContent side="right">{item.label}</TooltipContent>
                    </Tooltip>
                ) : (
                    interactive
                )}
            </li>
        );
    };

    return (
        <nav aria-label="Primary" className="flex flex-col gap-3">
            {groups.map((group, groupIndex) => {
                /*
                 * The index keeps the key unique even if a future group ships
                 * without a label. Every group gets a hairline separator except
                 * the first, so sections read as structure instead of as one
                 * long list with stray headings.
                 */
                const label = group.label;
                const collapsible = label !== undefined && group.items.length > 1;
                const panelId = `${baseId}-section-${groupIndex}`;
                const hasActiveItem = group.items.some((item) => active(item.href));

                /*
                 * Folded by default: a group opens while it owns the current
                 * route, or because the user left it open. The explicit choice
                 * wins, so an opened section survives a visit to another group.
                 * The icon rail has no room for a heading, let alone an arrow,
                 * so every group stays open there: the toggle is a full-width
                 * text affordance, not something a 72px column can carry.
                 */
                const isOpen =
                    collapsed || !collapsible || (sectionOverrides[label ?? ''] ?? hasActiveItem);

                return (
                    <div
                        key={label ?? `group-${groupIndex}`}
                        className={cn(groupIndex > 0 && 'border-t border-aq-content-line/60 pt-3')}
                    >
                        {label !== undefined &&
                            (collapsible && !collapsed ? (
                                <button
                                    type="button"
                                    onClick={() => setSectionOpen(label, !isOpen)}
                                    aria-expanded={isOpen}
                                    aria-controls={panelId}
                                    className="mb-0.5 flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-[10px] font-semibold tracking-[0.16em] text-surface-600 uppercase transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
                                >
                                    <span>{label}</span>

                                    {/*
                                     * Down when open, right when folded. The
                                     * accent only appears while the group is
                                     * closed over the current page, so the
                                     * folded section still says "you are here".
                                     */}
                                    <ChevronDown
                                        className={cn(
                                            'size-3.5 shrink-0 transition-transform duration-200 motion-reduce:transition-none',
                                            !isOpen && '-rotate-90',
                                            hasActiveItem && !isOpen
                                                ? 'text-aq-accent-deep dark:text-aq-accent'
                                                : undefined,
                                        )}
                                        aria-hidden="true"
                                    />
                                </button>
                            ) : (
                                <p
                                    className={cn(
                                        'px-2.5 pb-1.5 text-[10px] font-semibold tracking-[0.16em] text-surface-600 uppercase dark:text-surface-300',
                                        collapsed && 'sr-only',
                                    )}
                                >
                                    {label}
                                </p>
                            ))}

                        {/*
                         * The id stays on a wrapper that is always rendered, so
                         * `aria-controls` never dangles while the section is
                         * folded — a broken IDREF is an axe violation and some
                         * screen readers announce the control as invalid.
                         */}
                        <div id={panelId}>
                            {isOpen ? (
                                <ul
                                    className={cn(
                                        'flex flex-col gap-0.5',
                                        /*
                                         * Mounted only while open: a height
                                         * animation would have to keep the rows
                                         * in the DOM, where `overflow-hidden`
                                         * clips the focus ring and `inert` hides
                                         * them from AT for no benefit. A short
                                         * reveal is enough.
                                         */
                                        collapsible &&
                                            !collapsed &&
                                            'animate-in fade-in-0 slide-in-from-top-1 motion-reduce:animate-none',
                                    )}
                                >
                                    {group.items.map((item) =>
                                        renderItem(item, `${groupIndex}-${item.href}`),
                                    )}
                                </ul>
                            ) : null}
                        </div>
                    </div>
                );
            })}
        </nav>
    );
}
