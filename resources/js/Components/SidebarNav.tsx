/**
 * SidebarNav — the one navigation list, rendered identically by the desktop
 * rail and the mobile drawer. It owns no open/collapsed state and calls no
 * route: the shell passes the groups in and listens for `navigate`, so the same
 * list cannot drift between the two surfaces.
 *
 * Active state is never colour-only: a 2px accent bar, a tinted row and
 * `aria-current="page"` all fire together, and the row keeps its shape when the
 * rail collapses to icons (labels swap to `sr-only` and the tooltip takes over).
 */
import { Link } from '@inertiajs/react';
import {
    isActivePath,
    jumpToSection,
    sectionId,
    type NavGroup,
    type NavItem,
} from '@/lib/navigation';
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
        'group relative flex h-11 items-center gap-3 rounded-lg text-sm font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none',
        collapsed ? 'justify-center px-0' : 'px-3',
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
                        className="absolute inset-y-2 start-0 w-0.5 rounded-full bg-aq-accent"
                    />
                )}

                <item.icon
                    className={cn(
                        'h-4 w-4 shrink-0',
                        isItemActive
                            ? 'text-aq-accent-deep dark:text-aq-accent'
                            : 'text-surface-400 dark:text-surface-500',
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
        <nav aria-label="Primary" className="flex flex-col gap-5">
            {groups.map((group, groupIndex) => (
                /*
                 * Two groups carry no label — the primary destinations and the
                 * account group — so the index keeps the key unique. A label
                 * alone would collide and React would reuse one group's
                 * subtree for the other.
                 */
                <div key={group.label ?? `group-${groupIndex}`}>
                    {group.label && (
                        <p
                            className={cn(
                                'px-3 pb-1.5 text-[11px] font-semibold tracking-[0.14em] text-surface-600 uppercase dark:text-surface-300',
                                collapsed && 'sr-only',
                            )}
                        >
                            {group.label}
                        </p>
                    )}

                    <ul className="flex flex-col gap-0.5">
                        {group.items.map((item) => renderItem(item, `${groupIndex}-${item.href}`))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}
