/**
 * AppSidebar — primary navigation rail + mobile drawer.
 *
 * The rail is a plain `<aside>`, never a drawer: the drawer is only for the
 * breakpoint where a 256px column would eat the content. Collapse is
 * user-controlled and persisted (`aq:nav:collapsed`), and width is the only
 * thing that animates, CSS-only.
 *
 * Layout follows the reference sidebar: branding and the collapse control share
 * the top 64px row, destinations own the scrollable middle — Settings included,
 * as the last destination — and the account block closes the rail. The account
 * lives in the rail footer, so the header never repeats the identity.
 *
 * Collapsed state follows the hover-reveal rail pattern: the mark is the
 * default face of the expand control, and on hover or keyboard focus it swaps
 * to the open-panel arrow. The control is still a real button with an
 * accessible name, `aria-expanded` and `aria-controls`, so the hover is
 * enhancement rather than the only affordance.
 */
import { useEffect, useId } from 'react';
import { usePage } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import BrandLockup from '@/Components/BrandLockup';
import BrandMark from '@/Components/BrandMark';
import SidebarAccountFooter from '@/Components/SidebarAccountFooter';
import SidebarNav from '@/Components/SidebarNav';
import { Sheet, SheetContent, SheetTitle } from '@/Components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/Components/ui/tooltip';
import { dashboardGroups } from '@/lib/navigation';
import { useSidebar } from '@/lib/theme';
import { cn } from '@/lib/utils';

interface AppSidebarProps {
    /** Whether the mobile drawer is open. Owns the header trigger in the shell. */
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Current route path, used for the active row. */
    currentPath: string;
    /** Stable id the header trigger points at with `aria-controls`. */
    panelId: string;
}

/**
 * The top collapse/expand control.
 *
 * Expanded: a small panel icon beside the brand. Collapsed: the mark itself is
 * the button, and hovering or focusing it swaps the mark for the open-panel
 * arrow — the pattern popularised by icon rails (and the one the reference
 * screenshot points at). It keeps the 64px header to one row instead of
 * stacking an arrow under the logo.
 *
 * The swap is CSS-only (`group-hover` / `group-focus-visible`). The button keeps
 * its accessible name and state at all times, so a screen reader never depends
 * on the hover. Touch users do not get this rail at all — the mobile drawer is
 * always expanded — so no hover-only affordance is required there.
 */
function CollapseControl({
    collapsed,
    onToggle,
    controlsId,
}: {
    collapsed: boolean;
    onToggle: () => void;
    controlsId: string;
}) {
    if (collapsed) {
        return (
            <button
                type="button"
                onClick={onToggle}
                aria-label="Expand navigation"
                aria-expanded={false}
                aria-controls={controlsId}
                className="group relative grid size-10 place-items-center rounded-lg text-surface-500 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
            >
                <BrandMark
                    compact
                    className="absolute inset-0 m-auto transition-opacity duration-150 group-hover:opacity-0 group-focus-visible:opacity-0 motion-reduce:transition-none"
                />

                <PanelLeftOpen
                    className="absolute inset-0 m-auto size-4 opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </button>
        );
    }

    const label = 'Collapse navigation';

    const button = (
        <button
            type="button"
            onClick={onToggle}
            aria-label={label}
            aria-expanded={true}
            aria-controls={controlsId}
            className="grid size-8 shrink-0 place-items-center rounded-md text-surface-500 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
        >
            <PanelLeftClose className="size-4 shrink-0" aria-hidden="true" />
        </button>
    );

    return (
        <Tooltip>
            <TooltipTrigger render={button} />
            <TooltipContent side="bottom">{label}</TooltipContent>
        </Tooltip>
    );
}

/**
 * Settings is a normal rail row, rendered by `SidebarNav` from the navigation
 * model, so it reads as a destination rather than a special footer menu. The
 * footer below it belongs to the account.
 */
export default function AppSidebar({ open, onOpenChange, currentPath, panelId }: AppSidebarProps) {
    const page = usePage<{
        authPermissions?: string[];
        canManageStaff?: boolean;
        canManageAccess?: boolean;
    }>();
    const { collapsed, setCollapsed, isDesktop } = useSidebar();
    const railId = useId();

    const groups = dashboardGroups(currentPath, page.props.authPermissions ?? [], {
        manageStaff: page.props.canManageStaff === true,
        manageAccess: page.props.canManageAccess === true,
    });

    const closeDrawer = (): void => {
        if (!isDesktop) {
            onOpenChange(false);
        }
    };

    /*
     * The drawer state lives in the shell. Crossing to the desktop breakpoint
     * while it is open would leave a modal sheet over the rail, and the
     * drawer's own `closeDrawer` no longer fires there.
     */
    useEffect(() => {
        onOpenChange(false);
    }, [currentPath, isDesktop, onOpenChange]);

    const toggleCollapsed = (): void => setCollapsed(!collapsed);

    return (
        <>
            <aside
                id={railId}
                className={cn(
                    'sticky top-0 hidden h-dvh shrink-0 flex-col transition-[width] duration-200 ease-out motion-reduce:transition-none lg:flex',
                    /*
                     * The rail is one lightness step above the canvas — the same
                     * elevation rule the cards use — so its edge needs no line.
                     * `surface-0` on `surface-50` in light, `surface-900` on
                     * `surface-950` in dark. The old `border-e` was the only
                     * thing separating two nearly identical translucent fills.
                     */
                    'bg-aq-card border-e border-aq-content-line/80 shadow-(--aq-shadow-rail)',
                    'dark:bg-linear-to-b dark:from-aq-card dark:via-aq-card dark:to-surface-950',
                    collapsed ? 'w-18' : 'w-64',
                )}
                aria-label="Main navigation"
            >
                <div
                    className={cn(
                        'flex h-16 shrink-0 items-center',
                        collapsed ? 'justify-center px-2' : 'gap-2 px-3',
                    )}
                >
                    {collapsed ? (
                        <CollapseControl collapsed onToggle={toggleCollapsed} controlsId={railId} />
                    ) : (
                        <>
                            <BrandLockup className="min-w-0 flex-1" />
                            <CollapseControl
                                collapsed={false}
                                onToggle={toggleCollapsed}
                                controlsId={railId}
                            />
                        </>
                    )}
                </div>

                <div className="aq-scroll-quiet min-h-0 flex-1 overflow-y-auto p-3">
                    <SidebarNav
                        groups={groups}
                        currentPath={currentPath}
                        collapsed={collapsed}
                        onNavigate={closeDrawer}
                    />
                </div>

                {/*
                 * The account block closes the rail as a surface, not as a
                 * fenced-off strip: the old top border split one 900px column
                 * into three boxes. A rounded panel plus margin separates it
                 * from the scroll area without drawing another line, and the
                 * collapsed rail drops the panel because a 72px column has no
                 * room for a frame around one avatar.
                 */}
                <div
                    className={cn(
                        'shrink-0',
                        collapsed ? 'p-2' : 'mx-3 mb-3 border-t border-aq-content-line p-2',
                    )}
                >
                    <SidebarAccountFooter collapsed={collapsed} />
                </div>
            </aside>

            {open ? (
                <Sheet open={open} onOpenChange={onOpenChange}>
                    <SheetContent id={panelId} side="left" className="w-80 max-w-[85vw] gap-0 p-0">
                        <SheetTitle className="sr-only">Navigation</SheetTitle>

                        <div className="flex h-16 shrink-0 items-center px-4 pe-12">
                            <BrandLockup onClick={closeDrawer} />
                        </div>

                        <div className="aq-scroll-quiet min-h-0 flex-1 overflow-y-auto p-3">
                            <SidebarNav
                                groups={groups}
                                currentPath={currentPath}
                                collapsed={false}
                                onNavigate={closeDrawer}
                            />
                        </div>

                        <div className="mx-3 mb-3 shrink-0 border-t border-aq-content-line p-2">
                            <SidebarAccountFooter collapsed={false} />
                        </div>
                    </SheetContent>
                </Sheet>
            ) : (
                /*
                 * Keep the id the header trigger points at in the DOM while the
                 * drawer is closed; `aria-controls` must never be a dangling
                 * IDREF (the section panels use the same pattern).
                 */
                <div id={panelId} hidden />
            )}
        </>
    );
}
