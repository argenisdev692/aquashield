/**
 * The one navigation model.
 *
 * The desktop rail, the mobile drawer and the ⌘K palette all read from here, so
 * a destination can never exist in one surface and be missing from another. The
 * list is deliberately short: this app has exactly one authenticated route
 * (`/dashboard`) plus the sections that page actually renders. Adding a module
 * means adding its route here and nothing else.
 *
 * `anchor` entries are same-page targets, not Inertia visits. They only make
 * sense while the dashboard is mounted, which is why `AppSidebar` gates them on
 * `currentPath` instead of shipping a link that would do nothing on the auth
 * screens.
 */

export const DASHBOARD_PATH = '/dashboard';

export interface NavItem {
    readonly label: string;
    readonly href: string;
    /** PrimeIcons name without the `pi ` prefix, e.g. `'pi-home'`. */
    readonly icon: string;
    /** Extra words the palette filter should match but the row should not show. */
    readonly keywords: string;
    /** Permission required to see the destination; omitted for every-user rows. */
    readonly permission?: string;
    /** Same-page target (`#id`) instead of a route visit. */
    readonly anchor?: boolean;
}

export interface NavGroup {
    /** Omitted for the primary group, which needs no heading. */
    readonly label?: string;
    readonly items: readonly NavItem[];
}

export const PRIMARY_NAV: readonly NavItem[] = [
    {
        label: 'Dashboard',
        href: DASHBOARD_PATH,
        icon: 'pi-home',
        keywords: 'home overview start workspace pipeline crm',
    },
    {
        // The CompanyData module's one screen. Adding the module to the shell is
        // this entry and nothing else: the rail, the drawer and the palette read
        // from here.
        label: 'Company',
        href: '/company-data/edit',
        icon: 'pi-building',
        keywords: 'company profile settings branding contact address billing bank socials',
        permission: 'VIEW_COMPANY_DATA',
    },
    {
        label: 'Users',
        href: '/users',
        icon: 'pi-users',
        keywords: 'users team staff people accounts access roles permissions invitations',
        permission: 'VIEW_ANY_USER',
    },
];

/**
 * The menu is a courtesy, not a control: the route middleware still answers the
 * permission. Filtering here keeps a user without VIEW_ANY_USER from seeing a
 * destination that would only 403.
 */
export const visiblePrimaryNav = (permissions: readonly string[]): readonly NavItem[] => {
    const granted = new Set(permissions);

    return PRIMARY_NAV.filter(
        (item) => item.permission === undefined || granted.has(item.permission),
    );
};

/**
 * The dashboard's real section ids. `Dashboard.vue` owns the markup; these
 * hrefs are the scrolling contract between that page and the shell.
 */
export const DASHBOARD_SECTIONS: readonly NavItem[] = [
    {
        label: 'Key metrics',
        href: '#kpis',
        icon: 'pi-chart-bar',
        keywords: 'kpi leads open pipeline value won response time',
        anchor: true,
    },
    {
        label: 'Pipeline',
        href: '#pipeline',
        icon: 'pi-objects-column',
        keywords: 'kanban stages deals board drag calendar month appointments',
        anchor: true,
    },
    {
        label: 'Revenue',
        href: '#revenue',
        icon: 'pi-chart-line',
        keywords: 'won money months signed jobs chart',
        anchor: true,
    },
    {
        label: 'Schedule',
        href: '#schedule',
        icon: 'pi-calendar',
        keywords: 'today inspections estimates appointments field',
        anchor: true,
    },
];

/** `/dashboard` and everything beneath it, never a prefix of another path. */
export const isActivePath = (currentPath: string, href: string): boolean =>
    !href.startsWith('#') && (currentPath === href || currentPath.startsWith(`${href}/`));

export const sectionId = (href: string): string => href.replace(/^#/, '');

/**
 * Scrolls to a section with the offset the sections already declare via
 * `scroll-mt-24`, honouring reduced-motion. The palette and the rail share this
 * so the two cannot disagree about motion or target resolution.
 */
export const jumpToSection = (id: string): void => {
    const target = document.getElementById(id);

    if (target === null) {
        return;
    }

    target.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        block: 'start',
    });
};
